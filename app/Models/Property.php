<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'landlord_id', 'agent_id', 'title', 'description', 'property_type',
        'rent_amount', 'location_address', 'state', 'lga', 'longitude', 
        'latitude', 'facilities', 'status', 'verification_status'
    ];

    protected $casts = [
        'facilities' => 'array',
        'rent_amount' => 'decimal:2',
        'shelterbaze_commission' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'longitude' => 'decimal:8',
        'latitude' => 'decimal:8',
        'deleted_at' => 'datetime'
    ];

    protected $appends = ['primary_image'];

    // Constants
    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_RENTED = 'rented';

    const VERIFICATION_PENDING = 'pending';
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_REJECTED = 'rejected';

    const TYPE_1_BEDROOM = '1_bedroom';
    const TYPE_2_BEDROOM = '2_bedroom';
    const TYPE_3_BEDROOM = '3_bedroom';
    const TYPE_4_BEDROOM = '4_bedroom';
    const TYPE_STUDIO = 'studio';
    const TYPE_DUPLEX = 'duplex';
    const TYPE_BUNGALOW = 'bungalow';

    // Auto-calculate commission and total
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($property) {
            $commissionRate = config('app.shelterbaze_commission', 10.0) / 100;
            $property->shelterbaze_commission = $property->rent_amount * $commissionRate;
            $property->total_amount = $property->rent_amount + $property->shelterbaze_commission;
        });
    }

    // Relationships
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function media()
    {
        return $this->hasMany(PropertyMedia::class);
    }

    public function images()
    {
        return $this->hasMany(PropertyMedia::class)->where('media_type', 'image');
    }

    public function videos()
    {
        return $this->hasMany(PropertyMedia::class)->where('media_type', 'video');
    }

    public function engagementFees()
    {
        return $this->hasMany(EngagementFee::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function rentalAgreements()
    {
        return $this->hasMany(RentalAgreement::class);
    }

    public function rentSavings()
    {
        return $this->hasMany(RentSaving::class);
    }

    public function verifications()
    {
        return $this->hasMany(PropertyVerification::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function agentAssignments()
    {
        return $this->hasMany(AgentAssignment::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('property_type', $type);
    }

    public function scopeByLocation($query, $state, $lga = null)
    {
        $query->where('state', $state);
        if ($lga) {
            $query->where('lga', $lga);
        }
        return $query;
    }

    public function scopeByPriceRange($query, $min, $max)
    {
        return $query->whereBetween('rent_amount', [$min, $max]);
    }

    public function scopeWithFacilities($query, $facilities)
    {
        foreach ($facilities as $facility) {
            $query->whereJsonContains('facilities', $facility);
        }
        return $query;
    }

    // Accessors
    public function getPrimaryImageAttribute()
    {
        $primaryImage = $this->images()->where('is_primary', true)->first();
        return $primaryImage ? $primaryImage->media_url : null;
    }

    public function getFullAddressAttribute()
    {
        return $this->location_address . ', ' . $this->lga . ', ' . $this->state;
    }

    public function getFormattedPriceAttribute()
    {
        return '₦' . number_format($this->rent_amount, 2);
    }

    public function getFormattedTotalPriceAttribute()
    {
        return '₦' . number_format($this->total_amount, 2);
    }

    // Helper methods
    public function isAvailable()
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isVerified()
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    public function isRented()
    {
        return $this->status === self::STATUS_RENTED;
    }

    public function getInterestedTenants()
    {
        return User::whereIn('id', 
            $this->engagementFees()
                 ->where('payment_status', 'completed')
                 ->pluck('user_id')
        );
    }

    public function hasUserPaidEngagementFee($userId)
    {
        return $this->engagementFees()
                    ->where('user_id', $userId)
                    ->where('payment_status', 'completed')
                    ->exists();
    }

    public function isFavoritedBy($userId)
    {
        return $this->favorites()->where('user_id', $userId)->exists();
    }

    public function getDistance($latitude, $longitude)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        $earthRadius = 6371; // km
        $dLat = deg2rad($latitude - $this->latitude);
        $dLon = deg2rad($longitude - $this->longitude);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}
