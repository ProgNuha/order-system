<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

$loginThrottle = app()->environment('production') ? 'throttle:5,1' : 'throttle:60,1';

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware($loginThrottle);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});
