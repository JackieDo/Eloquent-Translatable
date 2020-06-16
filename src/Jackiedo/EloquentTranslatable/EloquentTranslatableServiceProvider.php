<?php

namespace Jackiedo\EloquentTranslatable;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Jackiedo\EloquentTranslatable\Console\Commands\CheckRepairCommand;
use Jackiedo\EloquentTranslatable\Validators\UniqueTranslationValidator;

/**
 * The EloquentTranslatableServiceProvider class.
 *
 * @package Jackiedo\EloquentTranslatable
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class EloquentTranslatableServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // Bootstrap handles
        $this->configHandle();

        Validator::extend('unique_translation', UniqueTranslationValidator::class . '@validate');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.eloquent-translatable.check-repair', function ($app) {
            return new CheckRepairCommand;
        });

        $this->commands('command.eloquent-translatable.check-repair');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.eloquent-translatable.check-repair',
        ];
    }

    /**
     * Loading and publishing package's config.
     *
     * @return void
     */
    protected function configHandle()
    {
        $packageConfigPath = __DIR__ . '/../../config/config.php';
        $appConfigPath     = config_path('eloquent-translatable.php');

        $this->mergeConfigFrom($packageConfigPath, 'eloquent-translatable');

        $this->publishes([
            $packageConfigPath => $appConfigPath,
        ], 'config');
    }
}
