<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
use App\Services\FirebaseService;

Route::get('/firebase-test', function (FirebaseService $firebase) {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];

    $response = $firebase->pushData('users', $data);

    if (!$response['success']) {
        dd('Error:', $response['error']);
    }

    dd('Success:', $response);
});
