<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'landlord_id', 'property_id', 'assignment_type',
        'status', 'assigned_by', 'assignment_notes', 'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime'
    ];

    // Constants
    const TYPE_LANDLORD_SUPPORT = 'landlord_support';
    const TYPE_PROPERTY_VERIFICATION = 'property_verification';

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeLandlordSupport($query)
    {
        return $query->where('assignment_type', self::TYPE_LANDLORD_SUPPORT);
    }

    public function scopePropertyVerification($query)
    {
        return $query->where('assignment_type', self::TYPE_PROPERTY_VERIFICATION);
    }

    // Helper methods
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isLandlordSupport()
    {
        return $this->assignment_type === self::TYPE_LANDLORD_SUPPORT;
    }

    public function isPropertyVerification()
    {
        return $this->assignment_type === self::TYPE_PROPERTY_VERIFICATION;
    }

    public function complete($notes = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'assignment_notes' => $notes
        ]);
    }

    public function cancel($notes = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'assignment_notes' => $notes
        ]);
    }
}
