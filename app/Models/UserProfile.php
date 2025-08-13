<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'nin_number', 'nin_selfie_url', 'address', 
        'state', 'lga', 'agent_id', 'id_card_url', 'verification_documents'
    ];

    protected $casts = [
        'verification_documents' => 'array'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getFullAddressAttribute()
    {
        return $this->address . ', ' . $this->lga . ', ' . $this->state;
    }

    // Helper methods
    public function isDocumentVerified()
    {
        return $this->nin_number && $this->nin_selfie_url;
    }

    public function generateAgentId()
    {
        if ($this->user->isAgent() && !$this->agent_id) {
            $this->agent_id = 'AGT' . str_pad($this->user->id, 6, '0', STR_PAD_LEFT);
            $this->save();
        }
        return $this->agent_id;
    }
}
