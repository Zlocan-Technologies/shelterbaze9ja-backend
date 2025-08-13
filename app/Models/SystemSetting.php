<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'value', 'description', 'type', 'is_public'
    ];

    protected $casts = [
        'is_public' => 'boolean'
    ];

    // Constants
    const TYPE_STRING = 'string';
    const TYPE_NUMBER = 'number';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    // Helper methods
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return self::castValue($setting->value, $setting->type);
    }

    public static function set($key, $value, $type = self::TYPE_STRING)
    {
        if ($type === self::TYPE_JSON) {
            $value = is_array($value) ? json_encode($value) : $value;
        }

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    public static function castValue($value, $type)
    {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case self::TYPE_NUMBER:
                return is_numeric($value) ? (float) $value : $value;
            case self::TYPE_JSON:
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    public function getCastedValueAttribute()
    {
        return self::castValue($this->value, $this->type);
    }
}