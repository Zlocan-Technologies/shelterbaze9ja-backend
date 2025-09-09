<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'code',
        'email',
        'expiry_time',
        'status'
    ];
}
