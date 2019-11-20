<?php

namespace Sarfraznawaz2005\Actions;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Sarfraznawaz2005\Actions\Console\MakeActionCommand;
use Sarfraznawaz2005\Actions\Console\MakeClassCommand;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('command.action.make', MakeActionCommand::class);
        $this->app->bind('command.class.make', MakeClassCommand::class);

        $this->commands([
            'command.action.make',
            'command.class.make',
        ]);
    }
}
