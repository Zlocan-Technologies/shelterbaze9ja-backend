<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngagementFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'property_id', 'amount', 'payment_reference', 
        'payment_status', 'payment_method', 'payment_data', 'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_data' => 'array',
        'paid_at' => 'datetime'
    ];

    // Constants
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const METHOD_PAYSTACK = 'paystack';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('payment_status', self::STATUS_FAILED);
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->payment_status === self::STATUS_COMPLETED;
    }

    public function isPending()
    {
        return $this->payment_status === self::STATUS_PENDING;
    }

    public function isFailed()
    {
        return $this->payment_status === self::STATUS_FAILED;
    }

    public function markAsCompleted()
    {
        $this->update([
            'payment_status' => self::STATUS_COMPLETED,
            'paid_at' => now()
        ]);
    }

    public function markAsFailed()
    {
        $this->update(['payment_status' => self::STATUS_FAILED]);
    }
}
