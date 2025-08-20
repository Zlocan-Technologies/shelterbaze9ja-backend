<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
