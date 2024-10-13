<?php

namespace Fateh\Phonsee;

use Illuminate\Support\ServiceProvider;

class FatehServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load the routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    public function register()
    {
        // Bind anything that needs to be resolved in the service container
    }
}
