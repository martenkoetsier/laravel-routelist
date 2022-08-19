<?php

namespace Martenkoetsier\LaravelRoutelist\Providers;

use Illuminate\Support\ServiceProvider;
use Martenkoetsier\LaravelRoutelist\RouteListCommand;

class LaravelRoutelistProvider extends ServiceProvider {
    /**
     * Bootstrap console command
     * 
     * @return void
     */
    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RouteListCommand::class,
            ]);
        }
    }
}
