<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'property_id', 'rental_agreement_id', 'ticket_number',
        'ticket_type', 'subject', 'description', 'status', 'priority',
        'assigned_to', 'attachments', 'resolution_notes', 'resolved_at'
    ];

    protected $casts = [
        'attachments' => 'array',
        'resolved_at' => 'datetime'
    ];

    // Constants
    const TYPE_GENERAL = 'general';
    const TYPE_PROPERTY_ISSUE = 'property_issue';
    const TYPE_PAYMENT_ISSUE = 'payment_issue';
    const TYPE_TECHNICAL = 'technical';
    const TYPE_ACCOUNT_ISSUE = 'account_issue';

    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';

    // Auto-generate ticket number
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($ticket) {
            $ticket->ticket_number = 'TKT-' . date('Y') . '-' . str_pad(self::count() + 1, 6, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function rentalAgreement()
    {
        return $this->belongsTo(RentalAgreement::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', self::PRIORITY_HIGH);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    // Helper methods
    public function isOpen()
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isInProgress()
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isResolved()
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isHighPriority()
    {
        return $this->priority === self::PRIORITY_HIGH;
    }

    public function assignTo($userId)
    {
        $this->update([
            'assigned_to' => $userId,
            'status' => self::STATUS_IN_PROGRESS
        ]);
    }

    public function resolve($notes)
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolution_notes' => $notes,
            'resolved_at' => now()
        ]);
    }

    public function close()
    {
        $this->update(['status' => self::STATUS_CLOSED]);
    }

    public function reopen()
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'resolved_at' => null
        ]);
    }

    public function getAgeInDaysAttribute()
    {
        return $this->created_at->diffInDays(now());
    }
}
