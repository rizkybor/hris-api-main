<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupService
{
    private const CHUNK_SIZE = 500;

    /**
     * Framework debug/telemetry tables that hold no business data and would
     * otherwise dwarf the actual backup with disposable request logs.
     */
    private const EXCLUDED_TABLES = [
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    /**
     * A dump under this size is almost certainly a truncated/failed write
     * (a real dump always has at least the header comments, FK/charset
     * pragmas, and this app's ~90+ tables' CREATE TABLE statements) rather
     * than a genuinely empty database -- catches a partial write (e.g. the
     * DB connection dropping mid-dump) instead of silently recording it as
     * a successful backup.
     */
    private const MIN_VALID_SIZE_BYTES = 2048;

    /**
     * Generate a full, gzip-compressed SQL dump and persist it to the
     * private local disk, recording it in the `backups` table so it shows
     * up in the backup history instead of only ever existing as a one-off
     * browser download.
     *
     * @param  int|null  $userId  null for scheduled/automated backups, which
     *   have no causing user
     */
    public function createAndStore(?int $userId, bool $isAutomatic = false): Backup
    {
        $filename = 'hris-backup-'.now()->format('Y-m-d_His').'.sql.gz';
        $diskPath = 'backups/'.$filename;

        Storage::disk('local')->makeDirectory('backups');
        $absolutePath = Storage::disk('local')->path($diskPath);

        $handle = gzopen($absolutePath, 'wb9');
        $this->streamDump($handle, gzip: true);
        gzclose($handle);

        $sizeBytes = Storage::disk('local')->size($diskPath);
        if ($sizeBytes < self::MIN_VALID_SIZE_BYTES) {
            Storage::disk('local')->delete($diskPath);

            throw new \RuntimeException("Backup dump came out suspiciously small ({$sizeBytes} bytes) and was discarded -- the database connection likely dropped mid-dump.");
        }

        return Backup::create([
            'filename' => $filename,
            'disk_path' => $diskPath,
            'size_bytes' => $sizeBytes,
            'created_by' => $userId,
            'is_automatic' => $isAutomatic,
        ]);
    }

    /**
     * Stream a full SQL dump of every table in the current database to the
     * given output resource (e.g. php://output).
     */
    public function streamDump($handle, bool $gzip = false): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $database = $connection->getDatabaseName();

        $write = $gzip ? 'gzwrite' : 'fwrite';

        $write($handle, "-- HRIS Database Backup\n");
        $write($handle, '-- Database: '.$database."\n");
        $write($handle, '-- Generated: '.now()->toDateTimeString()."\n\n");
        $write($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        $write($handle, "SET NAMES utf8mb4;\n\n");

        foreach ($this->getTableNames() as $table) {
            $this->dumpTable($handle, $pdo, $table, $write);
        }

        $write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * @return array<int, string>
     */
    private function getTableNames(): array
    {
        $rows = DB::select('SHOW TABLES');
        $database = DB::connection()->getDatabaseName();
        $key = 'Tables_in_'.$database;

        $tables = array_map(fn ($row) => $row->{$key}, $rows);

        return array_values(array_diff($tables, self::EXCLUDED_TABLES));
    }

    /**
     * chunk() requires an explicit orderBy; prefer the primary key, and
     * fall back to the table's first column when there isn't one.
     */
    private function getOrderColumn(string $table): string
    {
        $primaryKey = DB::selectOne("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        if ($primaryKey) {
            return $primaryKey->Column_name;
        }

        $columns = DB::select("SHOW COLUMNS FROM `{$table}`");

        return $columns[0]->Field;
    }

    private function dumpTable($handle, \PDO $pdo, string $table, callable $write): void
    {
        $write($handle, "-- ----------------------------\n");
        $write($handle, "-- Table structure for `{$table}`\n");
        $write($handle, "-- ----------------------------\n");

        $createRow = DB::selectOne("SHOW CREATE TABLE `{$table}`");
        $createSql = $createRow->{'Create Table'} ?? null;

        $write($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        if ($createSql) {
            $write($handle, $createSql.";\n\n");
        }

        $total = DB::table($table)->count();
        if ($total === 0) {
            $write($handle, "\n");

            return;
        }

        $write($handle, "-- ----------------------------\n");
        $write($handle, "-- Records of `{$table}` ({$total} rows)\n");
        $write($handle, "-- ----------------------------\n");

        $orderColumn = $this->getOrderColumn($table);

        DB::table($table)->orderBy($orderColumn)->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, $pdo, $table, $write) {
            if ($rows->isEmpty()) {
                return;
            }

            $columns = array_keys((array) $rows->first());
            $columnList = implode('`, `', $columns);

            $valueGroups = [];
            foreach ($rows as $row) {
                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $value);
                }, (array) $row);

                $valueGroups[] = '('.implode(', ', $values).')';
            }

            $write(
                $handle,
                "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n".implode(",\n", $valueGroups).";\n"
            );
        });

        $write($handle, "\n");
    }
}
