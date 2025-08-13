<?php
// =============================================================================
// FILE: app/Http/Controllers/API/AgentController.php
// =============================================================================
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AgentAssignment;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\RentalAgreement;
use App\Models\AuditLog;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgentController extends Controller
{
    private $fileUploadService;
    private $notificationService;

    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get landlords assigned to the authenticated agent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedLandlords(Request $request)
    {
        try {
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
                'total_properties_managed' => Property::whereIn('landlord_id',
                    AgentAssignment::where('agent_id', $agent->id)
                        ->where('assignment_type', 'landlord_support')
                        ->where('status', 'active')
                        ->pluck('landlord_id')
                )->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Assigned landlords retrieved successfully',
                'data' => [
                    'assignments' => $assignments,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assigned landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties assigned to the agent for verification
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedProperties(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Assigned properties retrieved successfully',
                'data' => [
                    'assignments' => $assignments,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assigned properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify a property
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyProperty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'verification_images' => 'required|array|min:3|max:10',
            'verification_images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'verification_notes' => 'required|string|max:1000',
            'longitude' => 'required|numeric|between:-180,180',
            'latitude' => 'required|numeric|between:-90,90',
            'status' => 'required|in:verified,rejected',
            'rejection_reason' => 'required_if:status,rejected|string|max:500',
            'property_condition' => 'sometimes|in:excellent,good,fair,poor',
            'accessibility_notes' => 'nullable|string|max:300',
            'surrounding_area_notes' => 'nullable|string|max:300'
        ], [
            'verification_images.min' => 'At least 3 verification images are required',
            'verification_images.max' => 'Maximum 10 verification images allowed',
            'verification_images.*.max' => 'Each image cannot exceed 5MB',
            'rejection_reason.required_if' => 'Rejection reason is required when rejecting a property'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = $request->user();
            $property = Property::with(['landlord'])->findOrFail($request->property_id);

            // Check if agent is assigned to verify this property
            $assignment = AgentAssignment::where('agent_id', $agent->id)
                ->where('property_id', $property->id)
                ->where('assignment_type', 'property_verification')
                ->where('status', 'active')
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to verify this property'
                ], 403);
            }

            // Check if property hasn't been verified already
            if ($property->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Property has already been verified'
                ], 400);
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
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload minimum required images'
                ], 500);
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

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'verification' => $verification->load('property'),
                    'property_status' => $property->fresh()->verification_status,
                    'location_accuracy' => $locationAccurate,
                    'images_uploaded' => count($imageUrls)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify an agent using their ID
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = User::whereHas('profile', function ($query) use ($request) {
                $query->where('agent_id', $request->agent_id);
            })
                ->with(['profile'])
                ->where('role', 'agent')
                ->where('account_status', 'active')
                ->first();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent not found or not active'
                ], 404);
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

            return response()->json([
                'success' => true,
                'message' => 'Agent verification successful',
                'data' => ['agent' => $agentData]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manage listing on behalf of landlord
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function manageListingForLandlord(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'landlord_id' => 'required|exists:users,id',
            'action' => 'required|in:create,update,delete,toggle_status',
            'property_id' => 'required_unless:action,create|exists:properties,id',
            // Property creation/update fields
            'title' => 'required_if:action,create|sometimes|string|max:255',
            'description' => 'required_if:action,create|sometimes|string|max:2000',
            'property_type' => 'required_if:action,create|sometimes|in:1_bedroom,2_bedroom,3_bedroom,4_bedroom,studio,duplex,bungalow',
            'rent_amount' => 'required_if:action,create|sometimes|numeric|min:1000',
            'location_address' => 'required_if:action,create|sometimes|string|max:500',
            'state' => 'required_if:action,create|sometimes|string|max:100',
            'lga' => 'required_if:action,create|sometimes|string|max:100',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:100',
            'images' => 'required_if:action,create|sometimes|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ], [
            'images.required_if' => 'At least one image is required when creating a property',
            'images.*.max' => 'Each image cannot exceed 2MB'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = $request->user();
            $landlord = User::findOrFail($request->landlord_id);

            // Check if agent is assigned to this landlord
            $assignment = AgentAssignment::where('agent_id', $agent->id)
                ->where('landlord_id', $landlord->id)
                ->where('assignment_type', 'landlord_support')
                ->where('status', 'active')
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to manage properties for this landlord'
                ], 403);
            }

            $result = [];

            switch ($request->action) {
                case 'create':
                    $result = $this->createPropertyForLandlord($landlord, $agent, $request);
                    break;
                
                case 'update':
                    $result = $this->updatePropertyForLandlord($landlord, $agent, $request);
                    break;
                
                case 'delete':
                    $result = $this->deletePropertyForLandlord($landlord, $agent, $request);
                    break;
                
                case 'toggle_status':
                    $result = $this->togglePropertyStatus($landlord, $agent, $request);
                    break;
                
                default:
                    throw new \InvalidArgumentException('Invalid action specified');
            }

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

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->action) . ' action completed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property management action failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verification history for the agent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerificationHistory(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Verification history retrieved successfully',
                'data' => [
                    'verifications' => $verifications,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verification history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent performance statistics and dashboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentStats(Request $request)
    {
        try {
            $agent = $request->user();

            // Core statistics
            $stats = [
                'total_verifications' => $agent->propertyVerifications()->count(),
                'verified_properties' => $agent->propertyVerifications()->verified()->count(),
                'rejected_properties' => $agent->propertyVerifications()->rejected()->count(),
                'active_landlord_assignments' => $agent->agentAssignments()->landlordSupport()->active()->count(),
                'active_property_assignments' => $agent->agentAssignments()->propertyVerification()->active()->count(),
                'completed_assignments' => $agent->agentAssignments()->completed()->count(),
                'properties_managed' => Property::where('agent_id', $agent->id)->count(),
                'total_properties_helped_rent' => RentalAgreement::whereHas('property', function ($query) use ($agent) {
                    $query->where('agent_id', $agent->id);
                })->where('status', 'active')->count()
            ];

            // Performance metrics
            $performance = [
                'verification_success_rate' => $this->calculateVerificationSuccessRate($agent->id),
                'average_verification_time' => $this->calculateAverageVerificationTime($agent->id),
                'monthly_verification_rate' => $this->calculateAverageVerificationsPerMonth($agent->id),
                'landlord_satisfaction_score' => $this->calculateLandlordSatisfactionScore($agent->id),
                'response_time_score' => $this->calculateResponseTimeScore($agent->id)
            ];

            // Recent activity (last 10 items)
            $recentVerifications = $agent->propertyVerifications()
                ->with('property:id,title,location_address')
                ->orderBy('verification_date', 'desc')
                ->limit(5)
                ->get();

            $pendingAssignments = $agent->agentAssignments()
                ->active()
                ->with(['property:id,title', 'landlord:id,first_name,last_name'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Monthly performance chart (last 12 months)
            $monthlyPerformance = PropertyVerification::selectRaw('
                    MONTH(verification_date) as month,
                    YEAR(verification_date) as year,
                    COUNT(*) as total_verifications,
                    SUM(CASE WHEN status = "verified" THEN 1 ELSE 0 END) as successful_verifications
                ')
                ->where('agent_id', $agent->id)
                ->where('verification_date', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            // Achievements and badges
            $achievements = $this->calculateAgentAchievements($agent->id, $stats, $performance);

            return response()->json([
                'success' => true,
                'message' => 'Agent statistics retrieved successfully',
                'data' => [
                    'stats' => $stats,
                    'performance' => $performance,
                    'recent_verifications' => $recentVerifications,
                    'pending_assignments' => $pendingAssignments,
                    'monthly_performance' => $monthlyPerformance,
                    'achievements' => $achievements,
                    'agent_level' => $this->calculateAgentLevel($stats, $performance)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update agent availability status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_available' => 'required|boolean',
            'availability_notes' => 'nullable|string|max:300',
            'unavailable_until' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = $request->user();

            // Update agent availability in profile or create availability record
            $availabilityData = [
                'is_available' => $request->is_available,
                'availability_notes' => $request->availability_notes,
                'unavailable_until' => $request->unavailable_until,
                'last_availability_update' => now()
            ];

            // Store in user profile or separate availability table
            $agent->profile()->update($availabilityData);

            // Log availability change
            AuditLog::log('agent_availability_updated', $agent, null, $availabilityData);

            // Notify admin about availability changes
            if (!$request->is_available) {
                $this->notificationService->createInAppNotification(
                    1, // Admin user ID
                    'Agent Unavailable',
                    "Agent {$agent->full_name} is now unavailable" . 
                    ($request->unavailable_until ? " until " . Carbon::parse($request->unavailable_until)->format('Y-m-d') : ""),
                    'warning'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Availability status updated successfully',
                'data' => [
                    'availability' => $availabilityData,
                    'agent' => $agent->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update availability status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's commission and earnings summary
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEarningsSummary(Request $request)
    {
        try {
            $agent = $request->user();

            // Calculate earnings from successful rentals
            $successfulRentals = RentalAgreement::whereHas('property', function ($query) use ($agent) {
                $query->where('agent_id', $agent->id);
            })
                ->where('status', 'active')
                ->with(['property', 'rentPayments' => function ($query) {
                    $query->where('status', 'verified');
                }])
                ->get();

            // Assuming 5% agent commission on successful rentals
            $agentCommissionRate = 0.05; // 5%
            
            $earnings = [
                'total_properties_rented' => $successfulRentals->count(),
                'total_rent_value_facilitated' => $successfulRentals->sum('rent_amount'),
                'estimated_commission_earned' => $successfulRentals->sum('rent_amount') * $agentCommissionRate,
                'verified_payments_facilitated' => $successfulRentals->sum(function ($rental) {
                    return $rental->rentPayments->sum('amount');
                }),
                'average_property_value' => $successfulRentals->avg('rent_amount') ?? 0
            ];

            // Monthly earnings breakdown
            $monthlyEarnings = RentalAgreement::selectRaw('
                    MONTH(created_at) as month,
                    YEAR(created_at) as year,
                    COUNT(*) as rentals_count,
                    SUM(rent_amount) as total_rent_value,
                    SUM(rent_amount * 0.05) as estimated_commission
                ')
                ->whereHas('property', function ($query) use ($agent) {
                    $query->where('agent_id', $agent->id);
                })
                ->where('status', 'active')
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            // Performance bonuses (based on verification success rate)
            $verificationRate = $this->calculateVerificationSuccessRate($agent->id);
            $performanceBonus = 0;
            if ($verificationRate >= 95) {
                $performanceBonus = $earnings['estimated_commission_earned'] * 0.1; // 10% bonus
            } elseif ($verificationRate >= 90) {
                $performanceBonus = $earnings['estimated_commission_earned'] * 0.05; // 5% bonus
            }

            $earnings['performance_bonus'] = $performanceBonus;
            $earnings['total_estimated_earnings'] = $earnings['estimated_commission_earned'] + $performanceBonus;
            $earnings['verification_success_rate'] = $verificationRate;

            return response()->json([
                'success' => true,
                'message' => 'Earnings summary retrieved successfully',
                'data' => [
                    'earnings' => $earnings,
                    'monthly_breakdown' => $monthlyEarnings,
                    'successful_rentals' => $successfulRentals->map(function ($rental) {
                        return [
                            'property_title' => $rental->property->title,
                            'rent_amount' => $rental->rent_amount,
                            'estimated_commission' => $rental->rent_amount * 0.05,
                            'rental_date' => $rental->created_at->format('Y-m-d'),
                            'status' => $rental->status
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch earnings summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a report or feedback
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:landlord_issue,property_issue,system_issue,suggestion,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'priority' => 'required|in:low,medium,high',
            'related_property_id' => 'nullable|exists:properties,id',
            'related_landlord_id' => 'nullable|exists:users,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,png,jpg,pdf|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = $request->user();
            $attachmentUrls = [];

            // Upload attachments if provided
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $attachment) {
                    $upload = $this->fileUploadService->uploadToCloudinary($attachment, 'agent_reports');
                    if ($upload['success']) {
                        $attachmentUrls[] = [
                            'url' => $upload['url'],
                            'filename' => $attachment->getClientOriginalName(),
                            'size' => $attachment->getSize()
                        ];
                    }
                }
            }

            // Create support ticket for the report
            $ticket = \App\Models\SupportTicket::create([
                'user_id' => $agent->id,
                'property_id' => $request->related_property_id,
                'ticket_type' => 'technical', // Map to existing ticket types
                'subject' => $request->subject,
                'description' => "Agent Report ({$request->report_type})\n\n" . $request->description,
                'priority' => $request->priority,
                'attachments' => $attachmentUrls,
                'status' => 'open'
            ]);

            // Log report submission
            AuditLog::log('agent_report_submitted', $ticket, null, [
                'report_type' => $request->report_type,
                'agent_id' => $agent->id,
                'related_property_id' => $request->related_property_id,
                'related_landlord_id' => $request->related_landlord_id
            ]);

            // Notify admin immediately for high priority reports
            if ($request->priority === 'high') {
                $this->notificationService->createInAppNotification(
                    1, // Admin user ID
                    'HIGH PRIORITY: Agent Report',
                    "Agent {$agent->full_name} has submitted a high priority {$request->report_type} report: {$request->subject}",
                    'error'
                );
            } else {
                $this->notificationService->createInAppNotification(
                    1, // Admin user ID
                    'Agent Report Submitted',
                    "Agent {$agent->full_name} has submitted a {$request->report_type} report: {$request->subject}",
                    'info'
                );
            }

            // Confirm to agent
            $this->notificationService->createInAppNotification(
                $agent->id,
                'Report Submitted',
                "Your {$request->report_type} report has been submitted successfully. Ticket #{$ticket->ticket_number}",
                'success'
            );

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'ticket' => $ticket,
                    'estimated_response_time' => match($request->priority) {
                        'high' => '2-4 hours',
                        'medium' => '4-8 hours',
                        'low' => '1-2 business days',
                        default => '1-2 business days'
                    }
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Report submission failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent training resources and progress
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTrainingResources(Request $request)
    {
        try {
            $agent = $request->user();

            // Simulate training modules (in real app, this would come from database)
            $trainingModules = [
                [
                    'id' => 1,
                    'title' => 'Property Verification Standards',
                    'description' => 'Learn the proper way to verify properties',
                    'duration' => '45 minutes',
                    'difficulty' => 'Beginner',
                    'completion_status' => 'completed',
                    'score' => 95
                ],
                [
                    'id' => 2,
                    'title' => 'Customer Service Excellence',
                    'description' => 'Best practices for dealing with landlords and tenants',
                    'duration' => '60 minutes',
                    'difficulty' => 'Intermediate',
                    'completion_status' => 'in_progress',
                    'score' => null
                ],
                [
                    'id' => 3,
                    'title' => 'Legal Compliance in Property Management',
                    'description' => 'Understanding legal requirements and regulations',
                    'duration' => '90 minutes',
                    'difficulty' => 'Advanced',
                    'completion_status' => 'not_started',
                    'score' => null
                ],
                [
                    'id' => 4,
                    'title' => 'Technology and Tools Training',
                    'description' => 'Mastering the Shelterbaze platform',
                    'duration' => '30 minutes',
                    'difficulty' => 'Beginner',
                    'completion_status' => 'completed',
                    'score' => 88
                ]
            ];

            // Calculate training progress
            $completedModules = collect($trainingModules)->where('completion_status', 'completed')->count();
            $totalModules = count($trainingModules);
            $averageScore = collect($trainingModules)->whereNotNull('score')->avg('score') ?? 0;

            $trainingProgress = [
                'overall_progress' => ($completedModules / $totalModules) * 100,
                'completed_modules' => $completedModules,
                'total_modules' => $totalModules,
                'average_score' => round($averageScore, 1),
                'certification_status' => $completedModules >= $totalModules && $averageScore >= 80 ? 'certified' : 'pending',
                'next_certification_date' => now()->addDays(30)->format('Y-m-d')
            ];

            // Recommended resources based on performance
            $recommendations = $this->getTrainingRecommendations($agent->id);

            return response()->json([
                'success' => true,
                'message' => 'Training resources retrieved successfully',
                'data' => [
                    'training_modules' => $trainingModules,
                    'progress' => $trainingProgress,
                    'recommendations' => $recommendations,
                    'achievements' => [
                        'certificates_earned' => $completedModules,
                        'total_training_hours' => 3.75, // Sum of completed module hours
                        'specializations' => ['Property Verification', 'Technology Platform']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch training resources',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper Methods

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
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
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
                    \App\Models\PropertyMedia::create([
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
            'title', 'description', 'property_type', 'rent_amount',
            'location_address', 'state', 'lga', 'longitude', 'latitude', 'facilities'
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
     * Calculate landlord satisfaction score
     */
    private function calculateLandlordSatisfactionScore($agentId)
    {
        // Placeholder - in real implementation, this would be based on ratings/feedback
        $successRate = $this->calculateVerificationSuccessRate($agentId);
        return min(5.0, ($successRate / 100) * 5 + 0.5);
    }

    /**
     * Calculate response time score
     */
    private function calculateResponseTimeScore($agentId)
    {
        $avgTime = $this->calculateAverageVerificationTime($agentId);
        
        if ($avgTime <= 24) return 5.0; // Excellent
        if ($avgTime <= 48) return 4.0; // Good
        if ($avgTime <= 72) return 3.0; // Average
        return 2.0; // Needs improvement
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
     * Calculate agent achievements
     */
    private function calculateAgentAchievements($agentId, $stats, $performance)
    {
        $achievements = [];

        if ($stats['total_verifications'] >= 100) {
            $achievements[] = [
                'title' => 'Century Club',
                'description' => '100+ property verifications',
                'icon' => '🏆',
                'earned_date' => '2024-01-15'
            ];
        }

        if ($performance['verification_success_rate'] >= 95) {
            $achievements[] = [
                'title' => 'Accuracy Expert',
                'description' => '95%+ verification success rate',
                'icon' => '🎯',
                'earned_date' => '2024-02-20'
            ];
        }

        if ($stats['active_landlord_assignments'] >= 10) {
            $achievements[] = [
                'title' => 'Landlord Champion',
                'description' => 'Managing 10+ landlord accounts',
                'icon' => '👥',
                'earned_date' => '2024-03-10'
            ];
        }

        return $achievements;
    }

    /**
     * Calculate agent level based on performance
     */
    private function calculateAgentLevel($stats, $performance)
    {
        $score = 0;
        
        // Points for verifications
        $score += min($stats['total_verifications'] * 2, 200);
        
        // Points for success rate
        $score += $performance['verification_success_rate'];
        
        // Points for assignments
        $score += $stats['active_landlord_assignments'] * 5;
        $score += $stats['completed_assignments'] * 3;

        if ($score >= 500) return ['level' => 'Expert', 'points' => $score, 'next_level_points' => null];
        if ($score >= 300) return ['level' => 'Professional', 'points' => $score, 'next_level_points' => 500];
        if ($score >= 150) return ['level' => 'Experienced', 'points' => $score, 'next_level_points' => 300];
        if ($score >= 50) return ['level' => 'Intermediate', 'points' => $score, 'next_level_points' => 150];
        
        return ['level' => 'Beginner', 'points' => $score, 'next_level_points' => 50];
    }

    /**
     * Get training recommendations based on performance
     */
    private function getTrainingRecommendations($agentId)
    {
        $recommendations = [];
        
        $successRate = $this->calculateVerificationSuccessRate($agentId);
        
        if ($successRate < 90) {
            $recommendations[] = [
                'title' => 'Property Verification Standards',
                'reason' => 'Improve verification accuracy',
                'priority' => 'high'
            ];
        }

        $responseTime = $this->calculateAverageVerificationTime($agentId);
        if ($responseTime > 48) {
            $recommendations[] = [
                'title' => 'Time Management for Agents',
                'reason' => 'Improve response time',
                'priority' => 'medium'
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'title' => 'Advanced Agent Techniques',
                'reason' => 'Continue professional development',
                'priority' => 'low'
            ];
        }

        return $recommendations;
    }
}