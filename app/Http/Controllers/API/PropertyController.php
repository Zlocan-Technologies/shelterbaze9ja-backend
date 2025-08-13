<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\Favorite;
use App\Models\AuditLog;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class PropertyController extends Controller
{
    private $fileUploadService;
    private $notificationService;

    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        try {
            $properties = QueryBuilder::for(Property::class)
                ->with(['landlord:id,first_name,last_name', 'agent:id,first_name,last_name', 'media', 'favorites'])
                ->allowedFilters([
                    'property_type',
                    'state',
                    'lga',
                    'status',
                    'verification_status',
                    AllowedFilter::exact('landlord_id'),
                    AllowedFilter::scope('price_range', 'byPriceRange'),
                    AllowedFilter::callback('facilities', function ($query, $value) {
                        foreach ((array) $value as $facility) {
                            $query->whereJsonContains('facilities', $facility);
                        }
                    }),
                    AllowedFilter::callback('location', function ($query, $value) {
                        $coordinates = explode(',', $value);
                        if (count($coordinates) === 2) {
                            $lat = (float) $coordinates[0];
                            $lng = (float) $coordinates[1];
                            // You can implement radius-based filtering here
                        }
                    })
                ])
                ->allowedSorts(['rent_amount', 'created_at', 'title'])
                ->defaultSort('-created_at')
                ->paginate($request->get('per_page', 15));

            // Add computed properties for authenticated users
            if ($request->user()) {
                $userId = $request->user()->id;
                $properties->getCollection()->transform(function ($property) use ($userId) {
                    $property->is_favorited = $property->isFavoritedBy($userId);
                    $property->has_paid_engagement_fee = $property->hasUserPaidEngagementFee($userId);
                    return $property;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $properties
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id, Request $request)
    {
        try {
            $property = Property::with([
                'landlord:id,first_name,last_name,email,phone_number',
                'agent:id,first_name,last_name,email,phone_number',
                'media',
                'favorites'
            ])->findOrFail($id);

            // Check if user has paid engagement fee to see contact details
            $showContactDetails = false;
            if ($request->user()) {
                $showContactDetails = $property->hasUserPaidEngagementFee($request->user()->id) || 
                                    $request->user()->id === $property->landlord_id ||
                                    $request->user()->isAdmin();
            }

            // Hide contact details if engagement fee not paid
            if (!$showContactDetails) {
                $property->landlord->makeHidden(['email', 'phone_number']);
                if ($property->agent) {
                    $property->agent->makeHidden(['email', 'phone_number']);
                }
            }

            // Add computed properties for authenticated users
            if ($request->user()) {
                $userId = $request->user()->id;
                $property->is_favorited = $property->isFavoritedBy($userId);
                $property->has_paid_engagement_fee = $property->hasUserPaidEngagementFee($userId);
                $property->interested_tenants_count = $property->getInterestedTenants()->count();
            }

            return response()->json([
                'success' => true,
                'data' => ['property' => $property]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'property_type' => 'required|in:1_bedroom,2_bedroom,3_bedroom,4_bedroom,studio,duplex,bungalow',
            'rent_amount' => 'required|numeric|min:1',
            'location_address' => 'required|string',
            'state' => 'required|string',
            'lga' => 'required|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'images' => 'required|array|min:5|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'videos' => 'nullable|array|max:3',
            'videos.*' => 'file|mimes:mp4,mov,avi|max:10240' // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Create property
            $property = Property::create([
                'landlord_id' => $user->id,
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

            // Upload images
            foreach ($request->file('images') as $index => $image) {
                $upload = $this->fileUploadService->uploadToCloudinary($image, 'properties/images');
                
                if ($upload['success']) {
                    PropertyMedia::create([
                        'property_id' => $property->id,
                        'media_type' => 'image',
                        'media_url' => $upload['url'],
                        'public_id' => $upload['public_id'] ?? null,
                        'is_primary' => $index === 0 // First image is primary
                    ]);
                }
            }

            // Upload videos if provided
            if ($request->hasfile('videos')) {
                foreach ($request->file('videos') as $video) {
                    $upload = $this->fileUploadService->uploadToCloudinary($video, 'properties/videos');
                    
                    if ($upload['success']) {
                        PropertyMedia::create([
                            'property_id' => $property->id,
                            'media_type' => 'video',
                            'media_url' => $upload['url'],
                            'public_id' => $upload['public_id'] ?? null,
                        ]);
                    }
                }
            }

            // Log property creation
            AuditLog::log('property_created', $property);

            // Create notification
            $this->notificationService->createInAppNotification(
                $user->id,
                'Property Listed',
                "Your property '{$property->title}' has been successfully listed.",
                'success'
            );

            return response()->json([
                'success' => true,
                'message' => 'Property created successfully',
                'data' => ['property' => $property->load('media')]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'property_type' => 'sometimes|in:1_bedroom,2_bedroom,3_bedroom,4_bedroom,studio,duplex,bungalow',
            'rent_amount' => 'sometimes|numeric|min:1',
            'location_address' => 'sometimes|string',
            'state' => 'sometimes|string',
            'lga' => 'sometimes|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'status' => 'sometimes|in:open,closed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $property = Property::findOrFail($id);
            $user = $request->user();

            // Check authorization
            if ($property->landlord_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this property'
                ], 403);
            }

            $oldData = $property->toArray();
            $property->update($request->only([
                'title', 'description', 'property_type', 'rent_amount',
                'location_address', 'state', 'lga', 'longitude', 'latitude',
                'facilities', 'status'
            ]));

            // Log property update
            AuditLog::log('property_updated', $property, $oldData, $property->fresh()->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Property updated successfully',
                'data' => ['property' => $property->fresh()->load('media')]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $property = Property::findOrFail($id);
            $user = $request->user();

            // Check authorization
            if ($property->landlord_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this property'
                ], 403);
            }

            // Check if property has active rental agreements
            if ($property->rentalAgreements()->active()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete property with active rental agreements'
                ], 400);
            }

            // Delete media files from Cloudinary
            foreach ($property->media as $media) {
                if ($media->public_id) {
                    $this->fileUploadService->deleteFromCloudinary($media->public_id);
                }
            }

            // Log property deletion
            AuditLog::log('property_deleted', $property);

            $property->delete();

            return response()->json([
                'success' => true,
                'message' => 'Property deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function myListings(Request $request)
    {
        try {
            $user = $request->user();

            $properties = Property::with(['media', 'engagementFees', 'rentalAgreements'])
                ->where('landlord_id', $user->id)
                ->orWhere('agent_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            // Add computed properties
            $properties->getCollection()->transform(function ($property) {
                $property->interested_tenants_count = $property->getInterestedTenants()->count();
                $property->total_revenue = $property->rentalAgreements()->sum('total_amount');
                return $property;
            });

            return response()->json([
                'success' => true,
                'data' => $properties
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadMedia(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'media' => 'required|file',
            'media_type' => 'required|in:image,video',
            'is_primary' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $property = Property::findOrFail($id);
            $user = $request->user();

            // Check authorization
            if ($property->landlord_id !== $user->id && $property->agent_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload media for this property'
                ], 403);
            }

            // Check media limits
            $mediaType = $request->media_type;
            $currentCount = $property->media()->where('media_type', $mediaType)->count();
            $maxCount = $mediaType === 'image' ? 10 : 3;

            if ($currentCount >= $maxCount) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum {$maxCount} {$mediaType}s allowed per property"
                ], 400);
            }

            // Upload media
            $folder = $mediaType === 'image' ? 'properties/images' : 'properties/videos';
            $upload = $this->fileUploadService->uploadToCloudinary($request->file('media'), $folder);

            if (!$upload['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload media',
                    'error' => $upload['error']
                ], 500);
            }

            // Create media record
            $media = PropertyMedia::create([
                'property_id' => $property->id,
                'media_type' => $mediaType,
                'media_url' => $upload['url'],
                'public_id' => $upload['public_id'] ?? null,
                'is_primary' => $request->get('is_primary', false)
            ]);

            // Set as primary if requested
            if ($request->get('is_primary', false)) {
                $media->makePrimary();
            }

            // Log media upload
            AuditLog::log('property_media_uploaded', $property, null, [
                'media_type' => $mediaType,
                'media_url' => $upload['url']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => ['media' => $media]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeMedia($mediaId, Request $request)
    {
        try {
            $media = PropertyMedia::findOrFail($mediaId);
            $property = $media->property;
            $user = $request->user();

            // Check authorization
            if ($property->landlord_id !== $user->id && $property->agent_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to remove this media'
                ], 403);
            }

            // Delete from Cloudinary
            if ($media->public_id) {
                $this->fileUploadService->deleteFromCloudinary($media->public_id);
            }

            // Log media removal
            AuditLog::log('property_media_removed', $property, null, [
                'media_type' => $media->media_type,
                'media_url' => $media->media_url
            ]);

            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media removal failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus($id, Request $request)
    {
        try {
            $property = Property::findOrFail($id);
            $user = $request->user();

            // Check authorization
            if ($property->landlord_id !== $user->id && $property->agent_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to change property status'
                ], 403);
            }

            $newStatus = $property->status === 'open' ? 'closed' : 'open';
            $property->update(['status' => $newStatus]);

            // Log status change
            AuditLog::log('property_status_changed', $property, null, ['new_status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => "Property status changed to {$newStatus}",
                'data' => ['property' => $property->fresh()]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Status toggle failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleFavorite($id, Request $request)
    {
        try {
            $property = Property::findOrFail($id);
            $user = $request->user();

            $isFavorited = Favorite::toggle($user->id, $property->id);

            $message = $isFavorited ? 'Property added to favorites' : 'Property removed from favorites';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['is_favorited' => $isFavorited]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle favorite',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getFavorites(Request $request)
    {
        try {
            $user = $request->user();

            $favorites = Property::with(['media', 'landlord:id,first_name,last_name'])
                ->whereHas('favorites', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $favorites
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch favorites',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
