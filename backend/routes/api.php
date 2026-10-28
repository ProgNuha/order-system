<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

$loginThrottle = app()->environment('production') ? 'throttle:5,1' : 'throttle:60,1';

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware($loginThrottle);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);

    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{order}/ship', [OrderController::class, 'ship']);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
});
