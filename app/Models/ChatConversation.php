<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id', 'user_id', 'landlord_id', 'agent_id', 'status', 'last_message_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime'
    ];

    // Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';

    // Relationships
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    // Helper methods
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function close()
    {
        $this->update(['status' => self::STATUS_CLOSED]);
    }

    public function reopen()
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function updateLastMessage()
    {
        $this->update(['last_message_at' => now()]);
    }

    public function getParticipants()
    {
        $participants = [$this->user, $this->landlord];
        
        if ($this->agent) {
            $participants[] = $this->agent;
        }
        
        return collect($participants)->filter();
    }
}
