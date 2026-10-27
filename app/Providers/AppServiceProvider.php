<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Route::resourceVerbs(['create' => 'neu', 'edit' => 'bearbeiten']);

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }
    }
}
