<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentSaving extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'property_id', 'plan_name', 'target_amount', 'current_amount',
        'due_date', 'status', 'early_withdrawal_penalty', 'deposit_charge',
        'is_external_property', 'external_property_details'
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'early_withdrawal_penalty' => 'decimal:2',
        'deposit_charge' => 'decimal:2',
        'due_date' => 'date',
        'is_external_property' => 'boolean'
    ];

    // Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class, 'savings_id');
    }

    public function deposits()
    {
        return $this->hasMany(SavingsTransaction::class, 'savings_id')
                    ->where('transaction_type', 'deposit');
    }

    public function withdrawals()
    {
        return $this->hasMany(SavingsTransaction::class, 'savings_id')
                    ->where('transaction_type', 'withdrawal');
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

    public function scopeDueSoon($query, $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where('due_date', '<=', now()->addDays($days));
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

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isDue()
    {
        return $this->due_date <= now();
    }

    public function canWithdraw()
    {
        return $this->isDue() || $this->isCompleted();
    }

    public function getProgressPercentageAttribute()
    {
        return ($this->current_amount / $this->target_amount) * 100;
    }

    public function getRemainingAmountAttribute()
    {
        return $this->target_amount - $this->current_amount;
    }

    public function getDaysUntilDueAttribute()
    {
        return now()->diffInDays($this->due_date, false);
    }

    public function getTotalDepositsAttribute()
    {
        return $this->deposits()->where('status', 'completed')->sum('amount');
    }

    public function getTotalWithdrawalsAttribute()
    {
        return $this->withdrawals()->where('status', 'completed')->sum('amount');
    }

    public function calculateEarlyWithdrawalPenalty()
    {
        if ($this->canWithdraw()) {
            return 0;
        }
        return ($this->current_amount * $this->early_withdrawal_penalty) / 100;
    }

    public function calculateDepositCharge($amount)
    {
        return ($amount * $this->deposit_charge) / 100;
    }

    public function addAmount($amount)
    {
        $this->increment('current_amount', $amount);
        
        if ($this->current_amount >= $this->target_amount) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    public function subtractAmount($amount)
    {
        $this->decrement('current_amount', $amount);
        
        if ($this->current_amount <= 0) {
            $this->update(['status' => self::STATUS_CANCELLED]);
        }
    }
}