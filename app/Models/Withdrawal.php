<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'bank_name',
        'account_name',
        'account_number',
        'status',
        'reason_for_rejection'
    ];

    protected $casts = [
        'amount' => 'float'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
