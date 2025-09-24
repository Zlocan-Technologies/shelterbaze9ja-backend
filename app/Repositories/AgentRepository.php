<?php

namespace App\Repositories;

use App\Http\Requests\Agent\VerifyPropertyRequest;
use App\Models\AgentAssignment;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ApiResponse;
use Illuminate\Http\Request;

class AgentRepository
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService,
    ) {}

    public function getAgents()
    {
        // Method to retrieve agents
    }

    public function getAgent($agent_id) {}

    public function getAssignedLandlords(Request $request)
    {
        $agent = $request->user();

        $assignments = AgentAssignment::with([
            'landlord' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'created_at', 'account_status');
            },
            'landlord.profile:user_id,address,state,lga',
            'landlord.properties' => function ($query) {
                $query->select('id', 'landlord_id', 'title', 'status', 'verification_status', 'rent_amount', 'created_at');
            }
        ])
            ->where('agent_id', $agent->id)
            ->where('assignment_type', 'landlord_support')
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('search'), function ($query, $search) {
                return $query->whereHas('landlord', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add computed properties
        $assignments->getCollection()->transform(function ($assignment) {
            if ($assignment->landlord) {
                $assignment->landlord->total_properties = $assignment->landlord->properties->count();
                $assignment->landlord->verified_properties = $assignment->landlord->properties
                    ->where('verification_status', 'verified')->count();
                $assignment->landlord->total_rent_value = $assignment->landlord->properties
                    ->where('status', 'open')->sum('rent_amount');
                $assignment->assignment_duration = $assignment->created_at->diffInDays(now());
            }
            return $assignment;
        });

        $summary = [
            'total_assignments' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'landlord_support')->count(),
            'active_assignments' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'landlord_support')
                ->where('status', 'active')->count(),
            'completed_assignments' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'landlord_support')
                ->where('status', 'completed')->count(),
            'total_properties_managed' => Property::whereIn(
                'landlord_id',
                AgentAssignment::where('agent_id', $agent->id)
                    ->where('assignment_type', 'landlord_support')
                    ->where('status', 'active')
                    ->pluck('landlord_id')
            )->count()
        ];

        return ApiResponse::respond(
            message: 'Assigned landlords retrieved successfully',
            data: [
                'assignments' => $assignments,
                'summary' => $summary
            ]
        );
    }


    public function getAgentAssignments($agent_id)
    {
        // Method to retrieve assignments for a specific agent
        return AgentAssignment::where('agent_id', $agent_id)->get();
    }

    public function assignAgent($property_id, $agent_id, $assign_type, $landlord_id): AgentAssignment|null
    {
        //assign agent to property
        if (AgentAssignment::where('property_id', $property_id)
            ->where('agent_id', $agent_id)
            ->where('assignment_type', $assign_type)
            ->where('status', AgentAssignment::STATUS_ACTIVE)
            ->exists()
        ) {
            return null; // Agent already assigned
        }

        $assignment = AgentAssignment::updateOrCreate([
            'assignment_type' => $assign_type,
            'agent_id' => $agent_id,
            'landlord_id' => $landlord_id,
            'property_id' => $property_id,
        ], [
            'agent_id' => $agent_id,
            'landlord_id' => $landlord_id,
            'property_id' => $property_id,
            'assignment_type' => $assign_type,
            'status' => AgentAssignment::STATUS_ACTIVE,
            'assigned_by' => auth()->id(),
        ]);

        return $assignment;
    }

    public function unAssignAgent($property_id, $assign_type): bool
    {
        $assignment = AgentAssignment::where('property_id', $property_id)
            ->where('assignment_type', $assign_type)
            ->first();

        if (!$assignment) {
            return false; // No active assignment found
        }

        $assignment->delete();

        return true;
    }

    public function verifyProperty(VerifyPropertyRequest $request)
    {
        $agent = $request->user();
        $property = Property::with(['landlord'])->findOrFail($request->property_id);

        // Check if agent is assigned to verify this property
        $assignment = AgentAssignment::where('agent_id', $agent->id)
            ->where('property_id', $property->id)
            ->where('assignment_type', 'property_verification')
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return ApiResponse::respond(
                message: 'You are not authorized to verify this property',
                status: false,
                statusCode: 403
            );
        }

        // Check if property hasn't been verified already
        if ($property->verification_status === 'verified') {
            return ApiResponse::respond(
                message: 'Property has already been verified',
                status: false,
                statusCode: 400
            );
        }

        // Upload verification images
        $imageUrls = [];
        foreach ($request->file('verification_images') as $index => $image) {
            $upload = $this->fileUploadService->uploadToCloudinary(
                $image,
                'property_verifications/' . $property->id
            );

            if ($upload['success']) {
                $imageUrls[] = [
                    'url' => $upload['url'],
                    'public_id' => $upload['public_id'] ?? null,
                    'order' => $index + 1,
                    'uploaded_at' => now()->toISOString()
                ];
            }
        }

        if (count($imageUrls) < 3) {
            return ApiResponse::respond(
                message: 'Failed to upload minimum required images',
                status: false,
                statusCode: 500
            );
        }

        // Validate location accuracy (basic check)
        $locationAccurate = $this->validatePropertyLocation(
            $property,
            $request->latitude,
            $request->longitude
        );

        // Create comprehensive verification record
        $verificationData = [
            'property_id' => $property->id,
            'agent_id' => $agent->id,
            'verification_images' => $imageUrls,
            'verification_notes' => $request->verification_notes,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'verification_date' => now(),
            'status' => $request->status,
            'rejection_reason' => $request->rejection_reason,
            'property_condition' => $request->property_condition ?? 'good',
            'accessibility_notes' => $request->accessibility_notes,
            'surrounding_area_notes' => $request->surrounding_area_notes,
            'location_accuracy' => $locationAccurate,
            'verification_metadata' => [
                'images_count' => count($imageUrls),
                'verification_duration' => $assignment->created_at->diffInMinutes(now()),
                'device_info' => $request->header('User-Agent'),
                'ip_address' => $request->ip()
            ]
        ];

        $verification = PropertyVerification::create($verificationData);

        // Update property verification status
        if ($request->status === 'verified') {
            $verification->verify();
            $message = 'Property verified successfully';
            $notificationType = 'success';
            $landlordMessage = "Your property '{$property->title}' has been successfully verified by our agent and is now live.";
        } else {
            $verification->reject($request->rejection_reason);
            $message = 'Property verification rejected';
            $notificationType = 'warning';
            $landlordMessage = "Your property '{$property->title}' verification was rejected. Reason: {$request->rejection_reason}";
        }

        // Complete the assignment
        $assignment->complete('Property verification completed: ' . $request->status);

        // Log verification
        AuditLog::log('property_verification_completed', $verification, null, [
            'verification_status' => $request->status,
            'images_uploaded' => count($imageUrls),
            'location_accuracy' => $locationAccurate
        ]);

        // Create notifications
        $this->notificationService->createInAppNotification(
            $agent->id,
            'Verification Completed',
            $message,
            $notificationType
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $property->landlord_id,
            'Property Verification Update',
            $landlordMessage,
            $notificationType
        );

        // For verified properties, notify admin
        if ($request->status === 'verified') {
            $this->notificationService->createInAppNotification(
                1, // Admin user ID
                'Property Verified',
                "Property '{$property->title}' has been verified by agent {$agent->full_name}.",
                'info'
            );
        }

        return ApiResponse::respond(
            message: $message,
            data: [
                'verification' => $verification->load('property'),
                'property_status' => $property->fresh()->verification_status,
                'location_accuracy' => $locationAccurate,
                'images_uploaded' => count($imageUrls)
            ]
        );
    }


    public function getAssignedProperties(Request $request)
    {
        $agent = $request->user();

        $assignments = AgentAssignment::with([
            'property.media',
            'property.landlord' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone_number');
            }
        ])
            ->where('agent_id', $agent->id)
            ->where('assignment_type', 'property_verification')
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('location'), function ($query, $location) {
                return $query->whereHas('property', function ($q) use ($location) {
                    $q->where('state', 'LIKE', "%{$location}%")
                        ->orWhere('lga', 'LIKE', "%{$location}%");
                });
            })
            ->when($request->get('priority'), function ($query, $priority) {
                // Prioritize by creation date for now, can be enhanced
                if ($priority === 'urgent') {
                    return $query->where('created_at', '<=', now()->subDays(3));
                }
                return $query;
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add computed properties
        $assignments->getCollection()->transform(function ($assignment) {
            if ($assignment->property) {
                $assignment->days_since_assignment = $assignment->created_at->diffInDays(now());
                $assignment->is_urgent = $assignment->days_since_assignment > 3;
                $assignment->property->primary_image = $assignment->property->media
                    ->where('is_primary', true)->first()?->media_url;
                $assignment->property->images_count = $assignment->property->media
                    ->where('media_type', 'image')->count();
            }
            return $assignment;
        });

        // Summary statistics
        $summary = [
            'total_assigned' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'property_verification')->count(),
            'pending_verification' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'property_verification')
                ->where('status', 'active')->count(),
            'completed_verifications' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'property_verification')
                ->where('status', 'completed')->count(),
            'urgent_assignments' => AgentAssignment::where('agent_id', $agent->id)
                ->where('assignment_type', 'property_verification')
                ->where('status', 'active')
                ->where('created_at', '<=', now()->subDays(3))->count(),
            'verification_rate' => $this->calculateVerificationRate($agent->id)
        ];

        return ApiResponse::respond(
            message: 'Assigned properties retrieved successfully',
            data: [
                'assignments' => $assignments,
                'summary' => $summary
            ]
        );
    }



    /**
     * Calculate verification rate for agent
     */
    private function calculateVerificationRate($agentId)
    {
        $total = AgentAssignment::where('agent_id', $agentId)
            ->where('assignment_type', 'property_verification')
            ->count();

        $completed = AgentAssignment::where('agent_id', $agentId)
            ->where('assignment_type', 'property_verification')
            ->where('status', 'completed')
            ->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }


    /**
     * Validate property location accuracy
     */
    private function validatePropertyLocation($property, $lat, $lng)
    {
        // Basic validation - in real app, use more sophisticated location verification
        if (!$property->latitude || !$property->longitude) {
            return true; // No reference to compare
        }

        $distance = $this->calculateDistance(
            $property->latitude,
            $property->longitude,
            $lat,
            $lng
        );

        // Allow up to 100 meters difference
        return $distance <= 0.1; // 0.1 km = 100 meters
    }

    /**
     * Calculate distance between two coordinates
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
