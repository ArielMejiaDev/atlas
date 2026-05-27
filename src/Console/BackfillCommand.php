<?php

namespace ArielMejiaDev\Atlas\Console;

use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Console\Command;

class BackfillCommand extends Command
{
    protected $signature = 'atlas:backfill
        {--model= : Fully qualified model class}
        {--chunk=500 : Number of records to process per chunk}
        {--id= : Process only this specific record ID}
        {--dry-run : Show what would be geocoded without saving}
        {--force : Re-geocode even when lat/lng are already set}';

    protected $description = 'Backfill latitude/longitude on existing records';

    public function handle(OfflineGeocoder $geocoder): int
    {
        $modelClass = $this->option('model') ?: config('atlas.model');

        if (! $modelClass) {
            $this->error('No model class specified. Use --model= or set atlas.model in config.');

            return self::FAILURE;
        }

        if (! class_exists($modelClass)) {
            $this->error("Model class not found: $modelClass");

            return self::FAILURE;
        }

        $columns = config('atlas.columns', []);
        $latCol = $columns['latitude'] ?? 'latitude';
        $lngCol = $columns['longitude'] ?? 'longitude';
        $addressCol = $columns['address'] ?? 'address';
        $deletedAtCol = $columns['deleted_at'] ?? 'deleted_at';
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $specificId = $this->option('id');

        $query = $modelClass::query();

        // Include soft-deleted records if column is configured
        if ($deletedAtCol !== null && method_exists($modelClass, 'withTrashed')) {
            $query->withTrashed();
        }

        // Filter by specific ID
        if ($specificId !== null) {
            $query->where((new $modelClass)->getKeyName(), $specificId);
        }

        // Skip already-geocoded unless --force
        if (! $force) {
            $query->where(function ($q) use ($latCol, $lngCol) {
                $q->whereNull($latCol)->orWhereNull($lngCol);
            });
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No records to geocode.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Processing $total records...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $methods = [];
        $geocoded = 0;
        $missed = 0;

        $query->chunkById($chunkSize, function ($records) use (
            $geocoder, $columns, $latCol, $lngCol,
            $dryRun, &$methods, &$geocoded, &$missed, $bar
        ) {
            foreach ($records as $model) {
                $input = [];
                foreach (['address', 'city', 'state', 'zip', 'country'] as $key) {
                    $col = $columns[$key] ?? $key;
                    $input[$key] = (string) ($model->{$col} ?? '');
                }

                $result = $geocoder->geocode($input);

                if ($result !== null) {
                    $methods[$result->method] = ($methods[$result->method] ?? 0) + 1;
                    $geocoded++;

                    if (! $dryRun) {
                        $model->forceFill([
                            $latCol => $result->latitude,
                            $lngCol => $result->longitude,
                        ])->saveQuietly();
                    }
                } else {
                    $missed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Geocoded: $geocoded | Missed: $missed");

        if (! empty($methods)) {
            $this->newLine();
            $this->info('Method breakdown:');
            arsort($methods);
            foreach ($methods as $method => $count) {
                $this->line("  $method: $count");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. No records were updated.');
        }

        return self::SUCCESS;
    }
}
