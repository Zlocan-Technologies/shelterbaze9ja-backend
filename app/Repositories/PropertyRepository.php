<?php

namespace App\Repositories;

use App\Http\Requests\Property\CreatePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Requests\Property\UploadMediaRequest;
use App\Models\AuditLog;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\RentalAgreement;
use App\Models\RentPayment;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PropertyRepository
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService
    ) {}


    public function getAllProperties(Request $request)
    {
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

        return ApiResponse::respond(
            data: $properties,
            message: 'Success!',
        );
    }

    public function createProperty(CreatePropertyRequest $request)
    {

        $user = $request->user();

        $this->checkUserIsLandLord($user);

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

        return ApiResponse::respond(
            data: $property->load('media'),
            message: 'Property created successfully',
            statusCode: 201
        );
    }

    public function getBookedApartments(Request $request)
    {
        $user = $request->user();
        $this->checkUserIsLandLord($user);

        $data = RentalAgreement::with('rentPayments', 'property', 'tenant', 'agent', 'supportTickets')
            ->where('status', RentalAgreement::STATUS_ACTIVE)
            ->where('landlord_id', $user->id)
            ->whereHas('rentPayments', function ($q) {
                $q->where('status', RentPayment::STATUS_VERIFIED);
            })->get()->map(function ($agreement) {
                return [
                    'rental_agreement' => $agreement,
                    'property' => $agreement->property,
                    'tenant' => $agreement->tenant,
                    'agent' => $agreement->agent,
                    'total_rent_paid' => $agreement->rentPayments()->where('status', RentPayment::STATUS_VERIFIED)->sum('amount'),
                    'next_payment_due_date' => optional($agreement->rentPayments()->where('status', RentPayment::STATUS_VERIFIED)->orderBy('payment_date', 'desc')->first())->next_due_date?->addMonth(),
                    'support_tickets' => $agreement->supportTickets
                ];
            });

        $totalPayments = RentalAgreement::where('landlord_id', $user->id)
            ->whereHas('rentPayments', function ($q) {
                $q->where('status', RentPayment::STATUS_VERIFIED);
            })->withSum(['rentPayments' => function ($q) {
                $q->where('status', RentPayment::STATUS_VERIFIED);
            }], 'amount')->get()->sum('rent_payments_sum_amount');

        $pendingPayments = RentalAgreement::where('landlord_id', $user->id)
            ->whereHas('rentPayments', function ($q) {
                $q->where('status', RentPayment::STATUS_PENDING)
                ->where('payment_type', RentPayment::TYPE_ONLINE);
            })->get();

        return ApiResponse::respond(
            data: [
                'booked_apartments' => $data,
                'total_payments' => $totalPayments,
                'pending_payments' => $pendingPayments,
            ],
            message: 'Success!',
        );
    }

    public function getPropertyById(Request $request, int $id)
    {
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

        return ApiResponse::respond(
            data: $property,
            message: 'Success!',
        );
    }

    public function updateProperty(UpdatePropertyRequest $request, $id)
    {
        $property = Property::findOrFail($id);
        $user = $request->user();

        // Check authorization
        if ($property->landlord_id !== $user->id && !$user->isAdmin()) {

            return ApiResponse::respond(
                status: false,
                message: 'Unauthorized to update this property',
                statusCode: 403
            );
        }

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
            'facilities',
            'status'
        ]));

        // Log property update
        AuditLog::log('property_updated', $property, $oldData, $property->fresh()->toArray());

        return ApiResponse::respond(
            data: $property->fresh()->load('media'),
            message: 'Property updated successfully',
        );
    }

    public function myListings(Request $request)
    {
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

        return ApiResponse::respond(
            message: 'Success!',
            data: $properties
        );
    }

    public function deleteProperty(Request $request, $id)
    {
        $property = Property::find($id);
        if ($property === null) {
            return ApiResponse::respond(
                message: 'Property not found',
                status: false,
                statusCode: 404
            );
        }
        $user = $request->user();

        // Check authorization
        if ($property->landlord_id !== $user->id && !$user->isAdmin()) {
            return ApiResponse::respond(
                message: 'Unauthorized to delete this property',
                status: false,
                statusCode: 403
            );
        }

        // Check if property has active rental agreements
        if ($property->rentalAgreements()->active()->exists()) {
            return ApiResponse::respond(
                message: 'Cannot delete property with active rental agreements',
                status: false,
                statusCode: 400
            );
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

        return ApiResponse::respond(
            message: 'Property deleted successfully',
        );
    }

    public function getUserFavoriteProperties(Request $request)
    {
        $user = $request->user();

        $favorites = Property::with(['media', 'landlord:id,first_name,last_name'])
            ->whereHas('favorites', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->paginate($request->get('per_page', 15));

        return ApiResponse::respond(
            data: $favorites
        );
    }

    public function toggleFavorite(Request $request, $id)
    {
        $property = Property::findOrFail($id);
        $user = $request->user();

        $isFavorited = Favorite::toggle($user->id, $property->id);

        $message = $isFavorited ? 'Property added to favorites' : 'Property removed from favorites';

        return ApiResponse::respond(
            message: $message,
            data: ['is_favorited' => $isFavorited]
        );
    }

    public function toggleStatus(Request $request, $id)
    {
        $property = Property::findOrFail($id);
        $user = $request->user();

        // Check authorization
        if ($property->landlord_id !== $user->id && $property->agent_id !== $user->id && !$user->isAdmin()) {
            return ApiResponse::respond(
                message: 'Unauthorized to change property status',
                status: false,
                statusCode: 403
            );
        }

        $newStatus = $property->status === 'open' ? 'closed' : 'open';
        $property->update(['status' => $newStatus]);

        // Log status change
        AuditLog::log('property_status_changed', $property, null, ['new_status' => $newStatus]);

        return ApiResponse::respond(
            message: "Property status changed to {$newStatus}",
            data: $property->fresh()
        );
    }

    public function removeMedia(Request $request, $mediaId)
    {
        $media = PropertyMedia::findOrFail($mediaId);
        $property = $media->property;
        $user = $request->user();

        // Check authorization
        if ($property->landlord_id !== $user->id && $property->agent_id !== $user->id && !$user->isAdmin()) {
            return ApiResponse::respond(
                message: 'Unauthorized to remove this media',
                status: false,
                statusCode: 403
            );
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
        return ApiResponse::respond(
            message: 'Media removed successfully',
        );
    }

    public function uploadMedia(UploadMediaRequest $request, $id)
    {
        $property = Property::findOrFail($id);
        $user = $request->user();

        // Check authorization
        if ($property->landlord_id !== $user->id && $property->agent_id !== $user->id && !$user->isAdmin()) {
            return ApiResponse::respond(
                message: 'Unauthorized to upload media for this property',
                status: false,
                statusCode: 403
            );
        }

        // Check media limits
        $mediaType = $request->media_type;
        $currentCount = $property->media()->where('media_type', $mediaType)->count();
        $maxCount = $mediaType === 'image' ? 10 : 3;

        if ($currentCount >= $maxCount) {
            return ApiResponse::respond(
                message: 'Maximum {$maxCount} {$mediaType}s allowed per property',
                status: false,
                statusCode: 400
            );
        }

        // Upload media
        $folder = $mediaType === 'image' ? 'properties/images' : 'properties/videos';
        $upload = $this->fileUploadService->uploadToCloudinary($request->file('media'), $folder);

        if (!$upload['success']) {
            return ApiResponse::respond(
                message: 'Failed to upload media',
                status: false,
                statusCode: 500
            );
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

        return ApiResponse::respond(
            message: 'Media uploaded successfully',
            data: ['media' => $media]
        );
    }


    private function checkUserIsLandLord($user)
    {
        if (!$user->isLandlord()) {
            throw new Exception('You are not a landlord!', 422);
        }
    }
}
