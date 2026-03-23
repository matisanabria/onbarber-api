<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\ScheduleOverrideController;
use Illuminate\Support\Facades\Route;

// Public endpoints (rate-limited, Barbman bypasses via bearer token)
Route::middleware('throttle:api')->group(function () {
    Route::get('/barbers', [BarberController::class, 'index']);
    Route::get('/barbers/{id}/slots', [BarberController::class, 'slots']);
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('throttle:booking');
});

// Barbman (protected — no rate limit, token-authenticated)
Route::middleware(['barbman.auth'])->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::patch('/appointments/{id}', [AppointmentController::class, 'update']);

    Route::get('/barbers/{barberId}/overrides', [ScheduleOverrideController::class, 'index']);
    Route::post('/schedule-overrides', [ScheduleOverrideController::class, 'store']);
    Route::delete('/schedule-overrides/{id}', [ScheduleOverrideController::class, 'destroy']);
});
