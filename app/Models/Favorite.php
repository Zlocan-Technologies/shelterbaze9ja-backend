<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'property_id'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Helper methods
    public static function toggle($userId, $propertyId)
    {
        $favorite = self::where('user_id', $userId)
                        ->where('property_id', $propertyId)
                        ->first();

        if ($favorite) {
            $favorite->delete();
            return false; // Removed from favorites
        } else {
            self::create([
                'user_id' => $userId,
                'property_id' => $propertyId
            ]);
            return true; // Added to favorites
        }
    }
}