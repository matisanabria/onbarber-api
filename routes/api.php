<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use Illuminate\Support\Facades\Route;

// Public endpoints
Route::get('/barbers', [BarberController::class, 'index']);
Route::get('/barbers/{id}/slots', [BarberController::class, 'slots']);
Route::post('/appointments', [AppointmentController::class, 'store'])
    ->middleware('throttle:booking');

// Barbman (protected)
Route::middleware(['throttle:auth', 'barbman.auth'])->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::patch('/appointments/{id}', [AppointmentController::class, 'update']);
});
