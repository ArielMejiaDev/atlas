<?php

namespace ArielMejiaDev\Atlas\Console;

use ArielMejiaDev\Atlas\Concerns\HasCoordinates;
use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Console\Command;

class BackfillCommand extends Command
{
    protected $signature = 'atlas:backfill
        {--model= : Fully qualified model class (must use HasCoordinates trait)}
        {--chunk=500 : Number of records to process per chunk}
        {--id= : Process only this specific record ID}
        {--dry-run : Show what would be geocoded without saving}
        {--force : Re-geocode even when coordinates already exist}';

    protected $description = 'Backfill coordinates on existing records';

    public function handle(OfflineGeocoder $geocoder): int
    {
        $modelClass = $this->option('model');

        if (! $modelClass) {
            $this->error('Please specify a model class with --model=');

            return self::FAILURE;
        }

        if (! class_exists($modelClass)) {
            $this->error("Model class not found: $modelClass");

            return self::FAILURE;
        }

        if (! in_array(HasCoordinates::class, class_uses_recursive($modelClass))) {
            $this->error("Model must use the HasCoordinates trait: $modelClass");

            return self::FAILURE;
        }

        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $specificId = $this->option('id');

        $query = $modelClass::query();

        // Include soft-deleted records when available
        if (method_exists($modelClass, 'withTrashed')) {
            $query->withTrashed();
        }

        // Filter by specific ID
        if ($specificId !== null) {
            $query->where((new $modelClass)->getKeyName(), $specificId);
        }

        // Skip already-geocoded unless --force
        if (! $force) {
            $query->whereDoesntHave('coordinates');
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
            $geocoder, $dryRun, $force, &$methods, &$geocoded, &$missed, $bar
        ) {
            foreach ($records as $model) {
                $input = $model->toGeocodableArray();

                $result = $geocoder->geocode($input);

                if ($result !== null) {
                    $methods[$result->method] = ($methods[$result->method] ?? 0) + 1;
                    $geocoded++;

                    if (! $dryRun) {
                        $data = [
                            'latitude' => $result->latitude,
                            'longitude' => $result->longitude,
                            'method' => $result->method,
                            'geocoded_at' => now(),
                        ];

                        if ($force) {
                            $model->coordinates()->updateOrCreate([], $data);
                        } else {
                            $model->coordinates()->create($data);
                        }
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
