<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id', 'tenant_id', 'landlord_id', 'agent_id',
        'rent_amount', 'shelterbaze_commission', 'total_amount',
        'agreement_start_date', 'agreement_end_date', 'status', 'terms_conditions'
    ];

    protected $casts = [
        'rent_amount' => 'decimal:2',
        'shelterbaze_commission' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'agreement_start_date' => 'date',
        'agreement_end_date' => 'date'
    ];

    // Constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_TERMINATED = 'terminated';

    // Relationships
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function rentPayments()
    {
        return $this->hasMany(RentPayment::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeExpiring($query, $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where('agreement_end_date', '<=', now()->addDays($days));
    }

    // Helper methods
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired()
    {
        return $this->status === self::STATUS_EXPIRED || $this->agreement_end_date < now();
    }

    public function isExpiring($days = 30)
    {
        return $this->isActive() && $this->agreement_end_date <= now()->addDays($days);
    }

    public function getDaysUntilExpiryAttribute()
    {
        return now()->diffInDays($this->agreement_end_date, false);
    }

    public function getMonthlyPaymentAttribute()
    {
        return $this->total_amount / 12; // Assuming yearly rent
    }

    public function getTotalPaidAttribute()
    {
        return $this->rentPayments()->where('status', 'verified')->sum('amount');
    }

    public function getOutstandingAmountAttribute()
    {
        return $this->total_amount - $this->getTotalPaidAttribute();
    }

    public function getLastPaymentAttribute()
    {
        return $this->rentPayments()->where('status', 'verified')->latest('payment_date')->first();
    }

    public function getNextPaymentDueAttribute()
    {
        $lastPayment = $this->getLastPaymentAttribute();
        if (!$lastPayment) {
            return $this->agreement_start_date;
        }
        return $lastPayment->next_due_date;
    }
}