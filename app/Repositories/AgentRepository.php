<?php

namespace App\Repositories;

use App\Http\Requests\Agent\ManageListingForLandLordRequest;
use App\Http\Requests\Agent\VerifyPropertyRequest;
use App\Models\AgentAssignment;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyVerification;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'assigned_by' => auth('sanctum')->user()->id,
        ]);

        //assign the agent to all the landlord's properties if not already assigned
        if($assign_type == AgentAssignment::TYPE_LANDLORD_SUPPORT) {
            Property::where('landlord_id', $landlord_id)->update(
                ['agent_id' => $agent_id]
            );
        }

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

        //unassign all properties assigned to the agent
        if($assign_type == AgentAssignment::TYPE_LANDLORD_SUPPORT) {
            Property::where('landlord_id', $assignment->landlord_id)->update(
                ['agent_id' => null]
            );
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
     * Verify an agent using their ID
     * 
     * @param Request $request
     * @return array
     */
    public function verifyAgent(Request $request)
    {
        $request->validate( [
            'agent_id' => 'required|string|max:20'
        ]);

        $agent = User::whereHas('profile', function ($query) use ($request) {
            $query->where('agent_id', $request->agent_id);
        })
            ->with(['profile'])
            ->where('role', 'agent')
            ->where('account_status', 'active')
            ->first();

        if (!$agent) {
            return ApiResponse::respond(
                message: 'Agent not found or not active',
                status: false,
                statusCode: 404
            );
        }

        // Get agent performance statistics
        $performanceStats = $this->getAgentPerformanceStats($agent->id);

        // Get agent details with verification info
        $agentData = [
            'agent_id' => $agent->profile->agent_id,
            'full_name' => $agent->full_name,
            'email' => $agent->email,
            'phone_number' => $agent->phone_number,
            'address' => $agent->profile->full_address,
            'state' => $agent->profile->state,
            'lga' => $agent->profile->lga,
            'verification_status' => $agent->account_status,
            'member_since' => $agent->created_at->format('Y-m-d'),
            'profile_image' => $agent->profile->nin_selfie_url,
            'id_card_url' => $agent->profile->id_card_url,
            'performance' => $performanceStats,
            'verification_badge' => $this->getVerificationBadge($performanceStats),
            'is_verified' => true,
            'verification_details' => [
                'nin_verified' => !empty($agent->profile->nin_number),
                'address_verified' => !empty($agent->profile->address),
                'phone_verified' => !empty($agent->phone_verified_at),
                'email_verified' => !empty($agent->email_verified_at)
            ]
        ];

        // Log agent verification check
        AuditLog::log('agent_verification_checked', $agent, null, [
            'checked_by' => $request->user()?->id,
            'agent_id' => $request->agent_id
        ]);

        return ApiResponse::respond(
            message: 'Agent verification successful',
            data: ['agent' => $agentData]
        );
    }

    /**
     * Manage listing on behalf of landlord
     * 
     * @param Request $request
     * @return array
     */
    public function manageListingForLandlord(ManageListingForLandLordRequest $request)
    {

        $agent = $request->user();
        $landlord = User::findOrFail($request->landlord_id);

        // Check if agent is assigned to this landlord
        $assignment = AgentAssignment::where('agent_id', $agent->id)
            ->where('landlord_id', $landlord->id)
            ->where('assignment_type', 'landlord_support')
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            throw new Exception("You are not authorized to manage listings for this landlord", 403);
        }

        $result = match ($request->action) {
            'create' => $this->createPropertyForLandlord($landlord, $agent, $request),
            'update' => $this->updatePropertyForLandlord($landlord, $agent, $request),
            'delete' => $this->deletePropertyForLandlord($landlord, $agent, $request),
            'toggle_status' => $this->togglePropertyStatus($landlord, $agent, $request),
            default => throw new \InvalidArgumentException('Invalid action specified')
        };

        // Notify landlord of the action
        $actionMessages = [
            'create' => 'created a new property listing',
            'update' => 'updated one of your property listings',
            'delete' => 'removed one of your property listings',
            'toggle_status' => 'changed the status of one of your property listings'
        ];

        $this->notificationService->createInAppNotification(
            $landlord->id,
            'Property Management Update',
            "Your agent has {$actionMessages[$request->action]} on your behalf.",
            'info'
        );

        return ApiResponse::respond(
            data: $result,
            message: ucfirst($request->action) . ' action completed successfully',
        );
    }

    /**
     * Get verification history for the agent
     * 
     * @param Request $request
     * @return array
     */
    public function getVerificationHistory(Request $request)
    {
        $agent = $request->user();

        $verifications = PropertyVerification::with([
            'property' => function ($query) {
                $query->select('id', 'title', 'location_address', 'state', 'lga', 'landlord_id');
            },
            'property.landlord:id,first_name,last_name'
        ])
            ->where('agent_id', $agent->id)
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('date_from'), function ($query, $dateFrom) {
                return $query->whereDate('verification_date', '>=', $dateFrom);
            })
            ->when($request->get('date_to'), function ($query, $dateTo) {
                return $query->whereDate('verification_date', '<=', $dateTo);
            })
            ->when($request->get('location'), function ($query, $location) {
                return $query->whereHas('property', function ($q) use ($location) {
                    $q->where('state', 'LIKE', "%{$location}%")
                        ->orWhere('lga', 'LIKE', "%{$location}%");
                });
            })
            ->orderBy('verification_date', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add computed properties
        $verifications->getCollection()->transform(function ($verification) {
            $verification->verification_age = $verification->verification_date->diffInDays(now());
            $verification->images_count = count($verification->verification_images);
            return $verification;
        });

        // Summary statistics
        $summary = [
            'total_verifications' => PropertyVerification::where('agent_id', $agent->id)->count(),
            'verified_properties' => PropertyVerification::where('agent_id', $agent->id)
                ->where('status', 'verified')->count(),
            'rejected_properties' => PropertyVerification::where('agent_id', $agent->id)
                ->where('status', 'rejected')->count(),
            'this_month_verifications' => PropertyVerification::where('agent_id', $agent->id)
                ->whereMonth('verification_date', now()->month)
                ->whereYear('verification_date', now()->year)->count(),
            'average_verifications_per_month' => $this->calculateAverageVerificationsPerMonth($agent->id),
            'verification_success_rate' => $this->calculateVerificationSuccessRate($agent->id)
        ];

        return [
            'success' => true,
            'message' => 'Verification history retrieved successfully',
            'data' => [
                'verifications' => $verifications,
                'summary' => $summary
            ]
        ];
    }

    /**
     * Calculate average verifications per month
     */
    private function calculateAverageVerificationsPerMonth($agentId)
    {
        $monthsActive = PropertyVerification::where('agent_id', $agentId)
            ->selectRaw('COUNT(DISTINCT DATE_FORMAT(verification_date, "%Y-%m")) as months')
            ->value('months') ?? 1;

        $totalVerifications = PropertyVerification::where('agent_id', $agentId)->count();

        return round($totalVerifications / max($monthsActive, 1), 1);
    }

    /**
     * Get agent performance statistics
     */
    private function getAgentPerformanceStats($agentId)
    {
        return [
            'total_verifications' => PropertyVerification::where('agent_id', $agentId)->count(),
            'successful_verifications' => PropertyVerification::where('agent_id', $agentId)
                ->where('status', 'verified')->count(),
            'success_rate' => $this->calculateVerificationSuccessRate($agentId),
            'average_response_time' => $this->calculateAverageVerificationTime($agentId),
            'total_properties_managed' => Property::where('agent_id', $agentId)->count(),
            'active_assignments' => AgentAssignment::where('agent_id', $agentId)
                ->where('status', 'active')->count(),
            'customer_rating' => $this->calculateLandlordSatisfactionScore($agentId)
        ];
    }

    /**
     * Get verification badge based on performance
     */
    private function getVerificationBadge($stats)
    {
        $successRate = $stats['success_rate'];
        $totalVerifications = $stats['total_verifications'];

        if ($successRate >= 98 && $totalVerifications >= 100) {
            return ['badge' => 'platinum', 'title' => 'Platinum Agent'];
        } elseif ($successRate >= 95 && $totalVerifications >= 50) {
            return ['badge' => 'gold', 'title' => 'Gold Agent'];
        } elseif ($successRate >= 90 && $totalVerifications >= 25) {
            return ['badge' => 'silver', 'title' => 'Silver Agent'];
        } elseif ($successRate >= 80 && $totalVerifications >= 10) {
            return ['badge' => 'bronze', 'title' => 'Bronze Agent'];
        }

        return ['badge' => 'none', 'title' => 'New Agent'];
    }

    /**
     * Calculate verification success rate
     */
    private function calculateVerificationSuccessRate($agentId)
    {
        $total = PropertyVerification::where('agent_id', $agentId)->count();
        $successful = PropertyVerification::where('agent_id', $agentId)->where('status', 'verified')->count();

        return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
    }

    /**
     * Calculate average verification time
     */
    private function calculateAverageVerificationTime($agentId)
    {
        // Placeholder - in real implementation, calculate based on assignment to completion time
        return rand(2, 48); // hours
    }

    /**
     * Calculate landlord satisfaction score
     */
    private function calculateLandlordSatisfactionScore($agentId)
    {
        // Placeholder - in real implementation, this would be based on ratings/feedback
        $successRate = $this->calculateVerificationSuccessRate($agentId);
        return min(5.0, ($successRate / 100) * 5 + 0.5);
    }

    /**
     * Create property on behalf of landlord
     */
    private function createPropertyForLandlord($landlord, $agent, $request)
    {
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'agent_id' => $agent->id,
            'title' => $request->title,
            'description' => $request->description,
            'property_type' => $request->property_type,
            'rent_amount' => $request->rent_amount,
            'location_address' => $request->location_address,
            'state' => $request->state,
            'lga' => $request->lga,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'facilities' => $request->facilities ?? [],
        ]);

        // Upload images if provided
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $upload = $this->fileUploadService->uploadToCloudinary($image, 'properties/images');
                if ($upload['success']) {
                    PropertyMedia::create([
                        'property_id' => $property->id,
                        'media_type' => 'image',
                        'media_url' => $upload['url'],
                        'public_id' => $upload['public_id'] ?? null,
                        'is_primary' => $index === 0
                    ]);
                }
            }
        }

        AuditLog::log('property_created_by_agent', $property, null, ['agent_id' => $agent->id]);

        return ['property' => $property->load('media')];
    }

    /**
     * Update property on behalf of landlord
     */
    private function updatePropertyForLandlord($landlord, $agent, $request)
    {
        $property = Property::where('landlord_id', $landlord->id)->findOrFail($request->property_id);

        $oldData = $property->toArray();
        $property->update($request->only([
            'title',
            'description',
            'property_type',
            'rent_amount',
            'location_address',
            'state',
            'lga',
            'longitude',
            'latitude',
            'facilities'
        ]));

        AuditLog::log('property_updated_by_agent', $property, $oldData, $property->fresh()->toArray());

        return ['property' => $property->fresh()];
    }

    /**
     * Delete property on behalf of landlord
     */
    private function deletePropertyForLandlord($landlord, $agent, $request)
    {
        $property = Property::where('landlord_id', $landlord->id)->findOrFail($request->property_id);

        if ($property->rentalAgreements()->active()->exists()) {
            throw new \Exception('Cannot delete property with active rental agreements');
        }

        AuditLog::log('property_deleted_by_agent', $property, null, ['agent_id' => $agent->id]);
        $property->delete();

        return ['message' => 'Property deleted successfully'];
    }

    /**
     * Toggle property status on behalf of landlord
     */
    private function togglePropertyStatus($landlord, $agent, $request)
    {
        $property = Property::where('landlord_id', $landlord->id)->findOrFail($request->property_id);

        $newStatus = $property->status === 'open' ? 'closed' : 'open';
        $property->update(['status' => $newStatus]);

        AuditLog::log('property_status_changed_by_agent', $property, null, [
            'agent_id' => $agent->id,
            'new_status' => $newStatus
        ]);

        return ['property' => $property->fresh(), 'new_status' => $newStatus];
    }

    /**
     * Get agent performance statistics and dashboard
     * 
     * @param Request $request
     * @return array
     */
    public function getAgentStats(Request $request)
    {
        $agent = $request->user();

        // Current month stats
        $currentMonth = now();
        $previousMonth = now()->subMonth();

        $stats = [
            'overview' => [
                'total_assignments' => AgentAssignment::where('agent_id', $agent->id)->count(),
                'active_assignments' => AgentAssignment::where('agent_id', $agent->id)
                    ->where('status', 'active')->count(),
                'completed_assignments' => AgentAssignment::where('agent_id', $agent->id)
                    ->where('status', 'completed')->count(),
                'total_verifications' => PropertyVerification::where('agent_id', $agent->id)->count(),
                'successful_verifications' => PropertyVerification::where('agent_id', $agent->id)
                    ->where('status', 'verified')->count(),
                'success_rate' => $this->calculateVerificationSuccessRate($agent->id)
            ],
            'monthly_performance' => [
                'current_month' => [
                    'verifications' => PropertyVerification::where('agent_id', $agent->id)
                        ->whereMonth('verification_date', $currentMonth->month)
                        ->whereYear('verification_date', $currentMonth->year)->count(),
                    'assignments_completed' => AgentAssignment::where('agent_id', $agent->id)
                        ->where('status', 'completed')
                        ->whereMonth('updated_at', $currentMonth->month)
                        ->whereYear('updated_at', $currentMonth->year)->count()
                ],
                'previous_month' => [
                    'verifications' => PropertyVerification::where('agent_id', $agent->id)
                        ->whereMonth('verification_date', $previousMonth->month)
                        ->whereYear('verification_date', $previousMonth->year)->count(),
                    'assignments_completed' => AgentAssignment::where('agent_id', $agent->id)
                        ->where('status', 'completed')
                        ->whereMonth('updated_at', $previousMonth->month)
                        ->whereYear('updated_at', $previousMonth->year)->count()
                ]
            ],
            'performance_metrics' => $this->getAgentPerformanceStats($agent->id),
            'recent_activities' => $this->getRecentAgentActivities($agent->id)
        ];

        return ApiResponse::respond(
            message: 'Agent statistics retrieved successfully',
            data: $stats
        );
    }

    /**
     * Update agent availability status
     * 
     * @param Request $request
     * @return array
     */
    public function updateAgentAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_available' => 'required|boolean',
            'availability_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), 422);
        }

        $agent = $request->user();

        // Update agent profile with availability status
        $agent->profile->update([
            'is_available' => $request->is_available,
            'availability_notes' => $request->availability_notes,
            'availability_updated_at' => now()
        ]);

        // Log availability change
        AuditLog::log('agent_availability_updated', $agent, null, [
            'is_available' => $request->is_available,
            'notes' => $request->availability_notes
        ]);

        // Notify admin of availability change
        $statusText = $request->is_available ? 'available' : 'unavailable';
        $this->notificationService->createInAppNotification(
            1, // Admin user ID
            'Agent Availability Update',
            "Agent {$agent->full_name} is now {$statusText}.",
            'info'
        );

        return ApiResponse::respond(
            message: 'Availability status updated successfully',
            data: [
                'is_available' => $request->is_available,
                'notes' => $request->availability_notes,
                'updated_at' => now()->toISOString()
            ]
        );
    }

    /**
     * Get agent's commission and earnings summary
     * 
     * @param Request $request
     * @return array
     */
    public function getAgentEarnings(Request $request)
    {
        $agent = $request->user();

        // Get earnings from rent payments and property verifications
        $earnings = [
            'total_earnings' => 0,
            'current_month_earnings' => 0,
            'pending_commissions' => 0,
            'paid_commissions' => 0,
            'verification_bonuses' => 0,
            'breakdown' => [
                'property_verifications' => $this->calculateVerificationEarnings($agent->id),
                'rental_commissions' => $this->calculateRentalCommissions($agent->id),
                'management_fees' => $this->calculateManagementFees($agent->id)
            ],
            'payment_history' => $this->getAgentPaymentHistory($agent->id),
            'next_payout_date' => now()->endOfMonth()->addDay()->format('Y-m-d')
        ];

        $earnings['total_earnings'] = array_sum(array_values($earnings['breakdown']));
        $earnings['current_month_earnings'] = $this->getCurrentMonthEarnings($agent->id);

        return ApiResponse::respond(
            message: 'Agent earnings retrieved successfully',
            data: $earnings
        );
    }

    /**
     * Submit a report or feedback
     * 
     * @param Request $request
     * @return array
     */
    public function submitAgentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:property_issue,landlord_feedback,system_issue,general_feedback',
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:2000',
            'priority' => 'required|in:low,medium,high,urgent',
            'property_id' => 'nullable|exists:properties,id',
            'landlord_id' => 'nullable|exists:users,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120'
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first(), 422);
        }

        $agent = $request->user();

        // Upload attachments if provided
        $attachmentUrls = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $upload = $this->fileUploadService->uploadToCloudinary(
                    $attachment,
                    'agent_reports/' . $agent->id
                );
                if ($upload['success']) {
                    $attachmentUrls[] = [
                        'url' => $upload['url'],
                        'filename' => $attachment->getClientOriginalName(),
                        'size' => $attachment->getSize(),
                        'uploaded_at' => now()->toISOString()
                    ];
                }
            }
        }

        // Create support ticket for the report
        $reportData = [
            'user_id' => $agent->id,
            'subject' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'category' => $request->report_type,
            'status' => 'open',
            'metadata' => [
                'property_id' => $request->property_id,
                'landlord_id' => $request->landlord_id,
                'attachments' => $attachmentUrls,
                'submitted_via' => 'agent_app'
            ]
        ];

        // For now, we'll use the existing support system
        // In a real app, you might have a dedicated AgentReport model
        AuditLog::log('agent_report_submitted', $agent, null, $reportData);

        // Notify admin of new report
        $this->notificationService->createInAppNotification(
            1, // Admin user ID
            'New Agent Report',
            "Agent {$agent->full_name} submitted a {$request->report_type} report: {$request->title}",
            $request->priority === 'urgent' ? 'warning' : 'info'
        );

        return ApiResponse::respond(
            message: 'Report submitted successfully',
            data: [
                'report_id' => uniqid('RPT'),
                'status' => 'submitted',
                'submitted_at' => now()->toISOString(),
                'attachments_count' => count($attachmentUrls)
            ]
        );
    }

    /**
     * Get agent training resources and progress
     * 
     * @param Request $request
     * @return array
     */
    public function getAgentTrainingResources(Request $request)
    {
        $agent = $request->user();

        // Mock training data - in real app, this would come from a training system
        $trainingResources = [
            'available_courses' => [
                [
                    'id' => 1,
                    'title' => 'Property Verification Basics',
                    'description' => 'Learn the fundamentals of property verification',
                    'duration' => '45 minutes',
                    'type' => 'video',
                    'difficulty' => 'beginner',
                    'completion_status' => 'completed',
                    'completion_date' => '2024-01-15',
                    'certificate_url' => null
                ],
                [
                    'id' => 2,
                    'title' => 'Advanced Property Assessment',
                    'description' => 'Deep dive into property condition assessment',
                    'duration' => '1.5 hours',
                    'type' => 'video',
                    'difficulty' => 'intermediate',
                    'completion_status' => 'in_progress',
                    'progress_percentage' => 65,
                    'certificate_url' => null
                ],
                [
                    'id' => 3,
                    'title' => 'Customer Service Excellence',
                    'description' => 'Improve your landlord and tenant interactions',
                    'duration' => '2 hours',
                    'type' => 'interactive',
                    'difficulty' => 'intermediate',
                    'completion_status' => 'not_started',
                    'certificate_url' => null
                ]
            ],
            'training_progress' => [
                'total_courses' => 15,
                'completed_courses' => 8,
                'in_progress_courses' => 2,
                'certificates_earned' => 6,
                'training_hours' => 24.5,
                'last_activity' => now()->subDays(3)->toISOString()
            ],
            'achievements' => [
                [
                    'title' => 'First Verification',
                    'description' => 'Complete your first property verification',
                    'earned' => true,
                    'earned_date' => '2024-01-10'
                ],
                [
                    'title' => 'Quality Inspector',
                    'description' => '95% verification success rate',
                    'earned' => $this->calculateVerificationSuccessRate($agent->id) >= 95,
                    'earned_date' => $this->calculateVerificationSuccessRate($agent->id) >= 95 ? now()->format('Y-m-d') : null
                ],
                [
                    'title' => 'Training Enthusiast',
                    'description' => 'Complete 10 training courses',
                    'earned' => false,
                    'earned_date' => null
                ]
            ],
            'recommended_courses' => [
                'based_on_performance' => $this->getRecommendedCourses($agent->id),
                'trending' => ['Legal Compliance', 'Digital Marketing for Agents', 'Conflict Resolution']
            ]
        ];

        return [
            'success' => true,
            'message' => 'Training resources retrieved successfully',
            'data' => $trainingResources
        ];
    }

    /**
     * Get recent agent activities
     */
    private function getRecentAgentActivities($agentId)
    {
        return AuditLog::where('user_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->action,
                    'description' => $this->formatActivityDescription($log),
                    'timestamp' => $log->created_at->toISOString()
                ];
            });
    }

    /**
     * Calculate verification earnings
     */
    private function calculateVerificationEarnings($agentId)
    {
        $verifications = PropertyVerification::where('agent_id', $agentId)
            ->where('status', 'verified')
            ->count();

        // Mock calculation - ₦2000 per successful verification
        return $verifications * 2000;
    }

    /**
     * Calculate rental commissions
     */
    private function calculateRentalCommissions($agentId)
    {
        // Mock calculation - in real app, calculate based on rental agreements
        return rand(50000, 200000);
    }

    /**
     * Calculate management fees
     */
    private function calculateManagementFees($agentId)
    {
        // Mock calculation - monthly management fees
        return rand(20000, 80000);
    }

    /**
     * Get agent payment history
     */
    private function getAgentPaymentHistory($agentId)
    {
        // Mock payment history - in real app, get from payments table
        return [
            [
                'date' => now()->subMonth()->format('Y-m-d'),
                'amount' => 75000,
                'type' => 'monthly_commission',
                'status' => 'paid'
            ],
            [
                'date' => now()->subMonths(2)->format('Y-m-d'),
                'amount' => 82000,
                'type' => 'monthly_commission',
                'status' => 'paid'
            ]
        ];
    }

    /**
     * Get current month earnings
     */
    private function getCurrentMonthEarnings($agentId)
    {
        // Mock calculation for current month
        return rand(30000, 90000);
    }

    /**
     * Get recommended courses based on agent performance
     */
    private function getRecommendedCourses($agentId)
    {
        $successRate = $this->calculateVerificationSuccessRate($agentId);

        if ($successRate < 80) {
            return ['Property Verification Basics', 'Quality Control Standards'];
        } elseif ($successRate < 95) {
            return ['Advanced Property Assessment', 'Documentation Best Practices'];
        }

        return ['Leadership for Agents', 'Business Development'];
    }

    /**
     * Format activity description for audit logs
     */
    private function formatActivityDescription($log)
    {
        return match ($log->action) {
            'property_verification_completed' => 'Completed property verification',
            'agent_availability_updated' => 'Updated availability status',
            'agent_report_submitted' => 'Submitted a report',
            'property_created_by_agent' => 'Created a property listing',
            'property_updated_by_agent' => 'Updated a property listing',
            default => ucwords(str_replace('_', ' ', $log->action))
        };
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
