<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\GroupItemController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\PacklistController;
use App\Http\Controllers\PacklistExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SharingController;
use App\Http\Controllers\StarterTemplateController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TemplateItemController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [MagicLinkController::class, 'create'])->name('login');
    Route::post('/anmelden', [MagicLinkController::class, 'store'])->name('login.send');
    Route::get('/anmelden/link/{token}', [MagicLinkController::class, 'show'])->name('login.link');
    Route::post('/anmelden/link/{token}', [MagicLinkController::class, 'consume'])->name('login.consume');

    Route::get('/admin-anmeldung', [AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/admin-anmeldung', [AdminLoginController::class, 'store'])->name('admin.login.store');

    Route::get('/einladung/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitation.show');
    Route::post('/einladung/{token}', [InvitationAcceptanceController::class, 'store'])->name('invitation.accept');
});

// Täglicher Auslöser für die Erinnerung am Abfahrtstag (Plesk: geplante Aufgabe „URL abrufen“)
Route::get('/cron/abfahrt', [PushController::class, 'cron'])->middleware('throttle:10,1')->name('cron.departure');

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/abmelden', LogoutController::class)->name('logout');
    // Frischer CSRF-Token für die Offline-Warteschlange (die Seite kann aus dem Cache stammen).
    Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'))->name('csrf');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profil', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/profil/bild', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::post('/profil/zugaenge', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/profil/zugaenge/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
    Route::post('/push/abo', [PushController::class, 'store'])->name('push.store');
    Route::delete('/push/abo', [PushController::class, 'destroy'])->name('push.destroy');
    Route::post('/push/test', [PushController::class, 'test'])->middleware('throttle:5,1')->name('push.test');
    Route::get('/push/hinweise', [PushController::class, 'messages'])->name('push.messages');
    Route::get('/benutzer/{user}/bild', [ProfileController::class, 'showAvatar'])->name('users.avatar');

    Route::resource('reisen', TripController::class)
        ->parameters(['reisen' => 'trip'])
        ->names('trips')
        ->except('index');
    Route::get('/reisen/{trip}/bild', [TripController::class, 'cover'])->name('trips.cover');

    Route::prefix('/reisen/{trip}')->name('packlist.')->scopeBindings()->group(function () {
        Route::put('/module', [PacklistController::class, 'templates'])->name('templates');
        Route::put('/behaeltnisse', [PacklistController::class, 'containers'])->name('containers');
        Route::patch('/zuordnen', [PacklistController::class, 'assign'])->name('assign');
        Route::post('/kopieren', [PacklistController::class, 'copy'])->name('copy');
        Route::get('/drucken', [PacklistExportController::class, 'print'])->name('print');
        Route::get('/export', [PacklistExportController::class, 'text'])->name('export');
        Route::post('/eintraege', [PacklistController::class, 'store'])->name('store');
        Route::patch('/eintraege/{entry}', [PacklistController::class, 'update'])->name('update');
        Route::patch('/eintraege/{entry}/gepackt', [PacklistController::class, 'toggle'])->name('toggle');
        Route::patch('/eintraege/{entry}/rueckweg', [PacklistController::class, 'toggleReturn'])->name('return');
        Route::delete('/eintraege/{entry}', [PacklistController::class, 'destroy'])->name('destroy');
        Route::post('/eintraege/{entry}/zurueck', [PacklistController::class, 'restore'])->name('restore');
    });

    Route::resource('katalog', ItemController::class)
        ->parameters(['katalog' => 'item'])
        ->names('items')
        ->except('show');

    Route::get('/benachrichtigungen', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/benachrichtigungen/einstellungen', [NotificationController::class, 'settings'])->name('notifications.settings');
    Route::put('/benachrichtigungen/einstellungen', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');

    Route::prefix('/reisen/{trip}')->group(function () {
        Route::post('/gemeinsam', [GroupItemController::class, 'store'])->name('group-items.store');
        Route::post('/gemeinsam/{groupItem}/annehmen', [GroupItemController::class, 'claim'])->name('group-items.claim');
        Route::post('/gemeinsam/{groupItem}/abgeben', [GroupItemController::class, 'release'])->name('group-items.release');
        Route::post('/gemeinsam/{groupItem}/zurueckziehen', [GroupItemController::class, 'withdraw'])->name('group-items.withdraw');
        Route::put('/freigabe/packliste', [SharingController::class, 'packlist'])->name('sharing.packlist');
        Route::put('/freigabe/outfits', [SharingController::class, 'outfits'])->name('sharing.outfits');
        Route::post('/einladen', [TripInvitationController::class, 'store'])->name('trips.invite');

        Route::post('/rueckblick/fehlte', [ReviewController::class, 'missing'])->name('review.missing');
        Route::patch('/rueckblick/{entry}', [ReviewController::class, 'update'])->name('review.update');
        Route::post('/rueckblick/{entry}/vorlage', [ReviewController::class, 'addToTemplate'])->name('review.template.add');
        Route::delete('/rueckblick/{entry}/vorlage/{template}', [ReviewController::class, 'removeFromTemplate'])->name('review.template.remove');
    });

    // Outfit-Routen ohne scopeBindings: Zugehörigkeit von Tag und Slot prüft der OutfitController.
    Route::prefix('/reisen/{trip}')->name('outfits.')->group(function () {
        Route::patch('/outfits/tage/{day}', [OutfitController::class, 'updateDay'])->name('day');
        Route::post('/outfits/tage/{day}/vortag', [OutfitController::class, 'copyPrevious'])->name('copy');
        Route::post('/outfits/tage/{day}/slots', [OutfitController::class, 'storeSlot'])->name('slots.store');
        Route::delete('/outfits/slots/{slot}', [OutfitController::class, 'destroySlot'])->name('slots.destroy');
        Route::post('/outfits/slots/{slot}/teile', [OutfitController::class, 'storeItem'])->name('items.store');
        Route::delete('/outfits/slots/{slot}/teile/{item}', [OutfitController::class, 'destroyItem'])->name('items.destroy');
        Route::post('/outfits/uebernehmen', [OutfitController::class, 'apply'])->name('apply');
    });

    Route::put('/kategorien/fest/{category}', [CategoryController::class, 'updateBuiltin'])->name('categories.builtin.update');
    Route::resource('kategorien', CategoryController::class)
        ->parameters(['kategorien' => 'category'])
        ->names('categories')
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('behaeltnisse', ContainerController::class)
        ->parameters(['behaeltnisse' => 'container'])
        ->names('containers')
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('vorlagen', TemplateController::class)
        ->parameters(['vorlagen' => 'template'])
        ->names('templates')
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('/vorlagen/{template}/gegenstaende', [TemplateItemController::class, 'store'])->name('templates.items.store');
    Route::patch('/vorlagen/{template}/gegenstaende/{item}', [TemplateItemController::class, 'update'])->name('templates.items.update');
    Route::delete('/vorlagen/{template}/gegenstaende/{item}', [TemplateItemController::class, 'destroy'])->name('templates.items.destroy');

    Route::post('/start-vorlagen', [StarterTemplateController::class, 'store'])->name('starter-templates.store');
    Route::delete('/start-vorlagen', [StarterTemplateController::class, 'destroy'])->name('starter-templates.dismiss');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminController::class)->name('index');
        Route::post('/einladungen', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/einladungen/{invitation}/erneut', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('/einladungen/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        Route::post('/benutzer/{user}/sperren', [UserController::class, 'block'])->name('users.block');
        Route::post('/benutzer/{user}/entsperren', [UserController::class, 'unblock'])->name('users.unblock');
        Route::delete('/benutzer/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
