<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id', 'agent_id', 'verification_images', 'verification_notes',
        'longitude', 'latitude', 'verification_date', 'status', 'rejection_reason'
    ];

    protected $casts = [
        'verification_images' => 'array',
        'longitude' => 'decimal:8',
        'latitude' => 'decimal:8',
        'verification_date' => 'datetime'
    ];

    // Constants
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';

    // Relationships
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    // Helper methods
    public function isVerified()
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function verify()
    {
        $this->update(['status' => self::STATUS_VERIFIED]);
        
        // Update property verification status
        $this->property->update([
            'verification_status' => Property::VERIFICATION_VERIFIED,
            'verified_by' => $this->agent_id,
            'verified_at' => now()
        ]);
    }

    public function reject($reason)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason
        ]);
        
        // Update property verification status
        $this->property->update([
            'verification_status' => Property::VERIFICATION_REJECTED
        ]);
    }
}
