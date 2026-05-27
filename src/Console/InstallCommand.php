<?php

namespace ArielMejiaDev\Atlas\Console;

use ArielMejiaDev\Atlas\Support\Normalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PDO;

class InstallCommand extends Command
{
    protected $signature = 'atlas:install
        {--force : Overwrite the existing database file}
        {--from= : Download a prebuilt SQLite file from a URL instead of building}';

    protected $description = 'Build the Atlas geocoding database from GeoNames data';

    public function handle(): int
    {
        $outputPath = config('atlas.database_path');

        if (file_exists($outputPath) && ! $this->option('force')) {
            $this->error("Database already exists at: $outputPath");
            $this->info('Use --force to overwrite.');

            return self::FAILURE;
        }

        $fromUrl = $this->option('from');

        if ($fromUrl) {
            return $this->installFromUrl($fromUrl, $outputPath);
        }

        return $this->buildFromGeoNames($outputPath);
    }

    private function installFromUrl(string $url, string $outputPath): int
    {
        $this->info('Downloading prebuilt database...');

        $tempPath = $outputPath.'.download';

        try {
            $response = Http::timeout(120)->sink($tempPath)->get($url);

            if (! $response->successful()) {
                $this->error("Download failed: HTTP {$response->status()}");

                return self::FAILURE;
            }

            // Verify it's a valid SQLite database
            try {
                $pdo = new PDO('sqlite:'.$tempPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->query('SELECT COUNT(*) FROM cities');
                $count = (int) $stmt->fetchColumn();

                if ($count === 0) {
                    $this->error('Downloaded file appears to be empty (no cities found).');
                    @unlink($tempPath);

                    return self::FAILURE;
                }

                $pdo = null;
            } catch (\Throwable $e) {
                $this->error('Downloaded file is not a valid SQLite database: '.$e->getMessage());
                @unlink($tempPath);

                return self::FAILURE;
            }

            $dir = dirname($outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            rename($tempPath, $outputPath);
            $this->info("Database installed at: $outputPath ($count cities)");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            @unlink($tempPath);
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function buildFromGeoNames(string $outputPath): int
    {
        $this->info('Building Atlas geocoding database from GeoNames...');
        $this->info('This downloads ~25 MB and takes 30-90 seconds.');
        $this->newLine();

        $builder = new BuildDatabase(app(Normalizer::class));

        try {
            $builder->build($outputPath, function (string $message) {
                $this->info("  $message");
            });

            $this->newLine();
            $this->info("Database built successfully at: $outputPath");

            // Show file size
            if (file_exists($outputPath)) {
                $size = round(filesize($outputPath) / 1024 / 1024, 1);
                $this->info("File size: {$size} MB");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Build failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
