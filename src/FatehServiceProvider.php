<?php

namespace Fateh\Phonsee;

use Illuminate\Support\ServiceProvider;

class FatehServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    public function register()
    {
        //
    }
}
