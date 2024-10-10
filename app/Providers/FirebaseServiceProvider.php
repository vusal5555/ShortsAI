<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $path = config('firebase.credentials.path');

        if (!file_exists($path)) {
            die("Firebase credentials file does not exist at: " . $path);
        }

        $this->app->singleton('firebase', function ($app) use ($path) {
            return (new Factory)->withServiceAccount($path); // Use createApp() instead of create()
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
