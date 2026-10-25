<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Public Auth Routes
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.auth.login');
Route::get('roles', [AuthController::class, 'roles'])->name('api.auth.roles');
Route::get('users-by-type', [AuthController::class, 'usersByType'])->name('api.auth.users-by-type');

// Protected Auth Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
});
