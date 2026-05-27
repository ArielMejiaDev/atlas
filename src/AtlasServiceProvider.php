<?php

namespace ArielMejiaDev\Atlas;

use ArielMejiaDev\Atlas\Console\BackfillCommand;
use ArielMejiaDev\Atlas\Console\InstallCommand;
use ArielMejiaDev\Atlas\Listeners\GeocodeOnCreated;
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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');

            $this->commands([
                InstallCommand::class,
                BackfillCommand::class,
            ]);
        }

        if (config('atlas.listener.enabled')) {
            $modelClass = config('atlas.model');
            if ($modelClass && class_exists($modelClass)) {
                $modelClass::created(function ($model) {
                    GeocodeOnCreated::dispatch($model);
                });
            }
        }
    }
}
