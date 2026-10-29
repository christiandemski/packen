<?php

use App\Http\Controllers\Api\TripController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Schnittstelle für „unterwegs“ – Zugang per API-Token aus dem Profil.
Route::middleware([AuthenticateApiToken::class, 'throttle:60,1'])->name('api.')->group(function () {
    // Wem gehört der Token? Zum Prüfen beim Eintragen in der anderen App.
    Route::get('/me', fn (Request $request) => response()->json(['data' => ['name' => $request->user()->name]]))->name('me');
    Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
    Route::get('/trips/{trip:id}', [TripController::class, 'show'])->name('trips.show');
    Route::patch('/trips/{trip:id}', [TripController::class, 'update'])->name('trips.update');
    Route::delete('/trips/{trip:id}', [TripController::class, 'destroy'])->name('trips.destroy');
    Route::get('/trips/{trip:id}/summary', [TripController::class, 'summary'])->name('trips.summary');
});
