<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_agreement_id', 'user_id', 'amount', 'payment_type',
        'bank_account_number', 'bank_name', 'account_name', 'payment_proof_url',
        'payment_date', 'due_date', 'next_due_date', 'status',
        'rejection_reason', 'admin_notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
        'next_due_date' => 'date',
        'verified_at' => 'datetime'
    ];

    // Constants
    const TYPE_ONLINE = 'online';
    const TYPE_OFFLINE = 'offline';

    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';

    // Relationships
    public function rentalAgreement()
    {
        return $this->belongsTo(RentalAgreement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('status', '!=', self::STATUS_VERIFIED);
    }

    // Helper methods
    public function isVerified()
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isOverdue()
    {
        return $this->due_date < now() && !$this->isVerified();
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        return now()->diffInDays($this->due_date);
    }

    public function verify($adminId, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_by' => $adminId,
            'verified_at' => now(),
            'admin_notes' => $notes
        ]);
    }

    public function reject($adminId, $reason)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verified_by' => $adminId,
            'verified_at' => now(),
            'rejection_reason' => $reason
        ]);
    }
}