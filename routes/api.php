<?php

use App\Http\Controllers\Api\TripController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

// Schnittstelle für „unterwegs“ – Zugang per API-Token aus dem Profil.
Route::middleware([AuthenticateApiToken::class, 'throttle:60,1'])->name('api.')->group(function () {
    Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
    Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::patch('/trips/{trip}', [TripController::class, 'update'])->name('trips.update');
    Route::delete('/trips/{trip}', [TripController::class, 'destroy'])->name('trips.destroy');
    Route::get('/trips/{trip}/summary', [TripController::class, 'summary'])->name('trips.summary');
});
