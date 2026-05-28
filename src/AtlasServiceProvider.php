<?php

namespace ArielMejiaDev\Atlas;

use ArielMejiaDev\Atlas\Console\BackfillCommand;
use ArielMejiaDev\Atlas\Console\InstallCommand;
use ArielMejiaDev\Atlas\Listeners\GeocodeOnCreated;
use ArielMejiaDev\Atlas\Listeners\GeocodeOnUpdated;
use ArielMejiaDev\Atlas\Support\Normalizer;
use Illuminate\Support\ServiceProvider;

class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atlas.php', 'atlas');

        $this->app->singleton(Normalizer::class);

        $this->app->singleton(OfflineGeocoder::class, function ($app) {
            $config = $app['config']['atlas'];

            if ($config['manage_connection'] ?? true) {
                $app['config']->set("database.connections.{$config['connection_name']}", [
                    'driver' => 'sqlite',
                    'database' => $config['database_path'],
                    'prefix' => '',
                    'foreign_key_constraints' => false,
                ]);
            }

            $pdo = $app['db']->connection($config['connection_name'])->getPdo();

            return new OfflineGeocoder($pdo, $app->make(Normalizer::class), $config);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'atlas-migrations');

            $this->commands([
                InstallCommand::class,
                BackfillCommand::class,
            ]);
        }

        if (config('atlas.listener.enabled')) {
            $models = config('atlas.listener.models', []);

            foreach ($models as $modelClass) {
                if (! class_exists($modelClass)) {
                    continue;
                }

                $modelClass::created(function ($model) {
                    GeocodeOnCreated::dispatch($model);
                });

                if (config('atlas.listener.on_update', true)) {
                    $modelClass::updated(function ($model) {
                        if (method_exists($model, 'addressFieldsChanged') && $model->addressFieldsChanged()) {
                            GeocodeOnUpdated::dispatch($model);
                        }
                    });
                }
            }
        }
    }
}
