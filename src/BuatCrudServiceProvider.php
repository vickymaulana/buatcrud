<?php

namespace VickyMaulana\BuatCrud;

use Illuminate\Support\ServiceProvider;
use VickyMaulana\BuatCrud\Commands\BuatCrud;

class BuatCrudServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register the command
        $this->app->singleton('command.buatcrud', function () {
            return new BuatCrud();
        });

        $this->commands([
            'command.buatcrud',
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Publish any resources or configurations if needed
    }
}
