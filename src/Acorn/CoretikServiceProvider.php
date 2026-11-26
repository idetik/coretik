<?php

namespace Coretik\Acorn;

use Coretik\App;
use Illuminate\Support\ServiceProvider;

/**
 * Registered by Acorn package discovery (composer.json extra.laravel.providers).
 * coretik is still booted by the project (App::run()): the provider exposes it in the Acorn container.
 */
class CoretikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolved on each call: Acorn may boot before the project runs coretik
        $this->app->bind('coretik', fn () => App::instance());
        $this->app->alias('coretik', App::class);
    }
}
