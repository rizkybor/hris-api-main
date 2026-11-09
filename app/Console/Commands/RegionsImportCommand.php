<?php

namespace App\Console\Commands;

use App\Support\IndonesianRegions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import the Indonesian region codes from database/data/regions/*.csv
 * (see the README there). Idempotent: rows are upserted on code and codes
 * no longer in the files are removed, so running it twice gives the same
 * result.
 */
class RegionsImportCommand extends Command
{
    protected $signature = 'regions:import
        {--path= : Directory with provinces.csv, regencies.csv, districts.csv and villages.csv (default database/data/regions)}
        {--chunk=1000 : Rows per insert}';

    protected $description = 'Import Indonesian provinces, regencies, districts and villages';

    private const FILES = [
        'provinces.csv' => ['regions_provinces', ['code', 'name']],
        'regencies.csv' => ['regions_regencies', ['code', 'province_code', 'name']],
        'districts.csv' => ['regions_districts', ['code', 'regency_code', 'name']],
        'villages.csv' => ['regions_villages', ['code', 'district_code', 'name']],
    ];

    public function handle(): int
    {
        $directory = rtrim($this->option('path') ?: database_path('data/regions'), '/');
        $chunk = max(100, (int) $this->option('chunk'));

        foreach (self::FILES as $file => [$table, $columns]) {
            if (! is_file("{$directory}/{$file}")) {
                $this->error("Missing {$directory}/{$file}.");

                return self::FAILURE;
            }
        }

        foreach (self::FILES as $file => [$table, $columns]) {
            $handle = fopen("{$directory}/{$file}", 'r');
            $header = fgetcsv($handle, escape: '');
            if ($header !== $columns) {
                fclose($handle);
                $this->error("{$file}: expected columns ".implode(',', $columns).'.');

                return self::FAILURE;
            }

            $codes = [];
            $batch = [];
            $count = 0;
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if ($row === [null] || count($row) !== count($columns)) {
                    continue;
                }
                $record = array_combine($columns, array_map('trim', $row));
                $codes[$record['code']] = true;
                $batch[] = $record;
                $count++;

                if (count($batch) >= $chunk) {
                    DB::table($table)->upsert($batch, ['code'], array_slice($columns, 1));
                    $batch = [];
                }
            }
            fclose($handle);
            if ($batch) {
                DB::table($table)->upsert($batch, ['code'], array_slice($columns, 1));
            }

            // Remove codes that are no longer in the dataset.
            $removed = 0;
            DB::table($table)->select('code')->orderBy('code')->chunk(5000, function ($rows) use ($table, $codes, &$removed) {
                $stale = $rows->pluck('code')->reject(fn ($code) => isset($codes[$code]))->values()->all();
                if ($stale) {
                    $removed += DB::table($table)->whereIn('code', $stale)->delete();
                }
            });

            $this->line(sprintf('%-15s %6d rows%s', $table, $count, $removed ? " ({$removed} removed)" : ''));
        }

        IndonesianRegions::bumpVersion();
        $this->info('Regions imported.');

        return self::SUCCESS;
    }
}
