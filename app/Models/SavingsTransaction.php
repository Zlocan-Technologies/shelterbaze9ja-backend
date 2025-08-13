<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_id', 'user_id', 'amount', 'charge_amount', 'penalty_amount',
        'net_amount', 'transaction_type', 'is_early_withdrawal', 'payment_reference',
        'status', 'payment_method', 'payment_data', 'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charge_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'is_early_withdrawal' => 'boolean',
        'payment_data' => 'array'
    ];

    // Constants
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const METHOD_PAYSTACK = 'paystack';
    const METHOD_BANK_TRANSFER = 'bank_transfer';

    // Relationships
    public function savings()
    {
        return $this->belongsTo(RentSaving::class, 'savings_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeDeposits($query)
    {
        return $query->where('transaction_type', self::TYPE_DEPOSIT);
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('transaction_type', self::TYPE_WITHDRAWAL);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // Helper methods
    public function isDeposit()
    {
        return $this->transaction_type === self::TYPE_DEPOSIT;
    }

    public function isWithdrawal()
    {
        return $this->transaction_type === self::TYPE_WITHDRAWAL;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsCompleted()
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
        
        // Update savings amount
        if ($this->isDeposit()) {
            $this->savings->addAmount($this->net_amount);
        } else {
            $this->savings->subtractAmount($this->net_amount);
        }
    }

    public function markAsFailed()
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }
}
