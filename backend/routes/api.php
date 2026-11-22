<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrganizerEventController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

// ---- Public ----
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});
Route::get('/categories', [EventController::class, 'categories']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id');

// ---- Authenticated ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Student only
    Route::middleware('role:student')->group(function () {
        Route::get('/my/registrations', [RegistrationController::class, 'mine']);
        Route::post('/events/{id}/register', [RegistrationController::class, 'store'])->whereNumber('id');
        Route::delete('/registrations/{id}', [RegistrationController::class, 'destroy'])->whereNumber('id');
    });

    // Organizer only
    Route::middleware('role:organizer')->prefix('organizer')->group(function () {
        Route::get('/events', [OrganizerEventController::class, 'index']);
        Route::post('/events', [OrganizerEventController::class, 'store']);
        Route::put('/events/{id}', [OrganizerEventController::class, 'update'])->whereNumber('id');
        Route::post('/events/{id}/publish', [OrganizerEventController::class, 'publish'])->whereNumber('id');
        Route::post('/events/{id}/close', [OrganizerEventController::class, 'close'])->whereNumber('id');
        Route::get('/events/{id}/registrations', [OrganizerEventController::class, 'registrations'])->whereNumber('id');
        Route::put('/registrations/{id}/attendance', [OrganizerEventController::class, 'attendance'])->whereNumber('id');
    });
});
