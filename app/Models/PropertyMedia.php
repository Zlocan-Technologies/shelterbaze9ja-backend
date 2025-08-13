<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id', 'media_type', 'media_url', 'public_id', 'is_primary'
    ];

    protected $casts = [
        'is_primary' => 'boolean'
    ];

    // Constants
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';

    // Relationships
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Scopes
    public function scopeImages($query)
    {
        return $query->where('media_type', self::TYPE_IMAGE);
    }

    public function scopeVideos($query)
    {
        return $query->where('media_type', self::TYPE_VIDEO);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    // Helper methods
    public function isImage()
    {
        return $this->media_type === self::TYPE_IMAGE;
    }

    public function isVideo()
    {
        return $this->media_type === self::TYPE_VIDEO;
    }

    public function makePrimary()
    {
        // Remove primary status from other media of same property
        $this->property->media()->update(['is_primary' => false]);
        
        // Set this as primary
        $this->update(['is_primary' => true]);
    }
}
