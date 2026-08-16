<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

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
     * Stream a full SQL dump of every table in the current database to the
     * given output resource (e.g. php://output).
     */
    public function streamDump($handle): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $database = $connection->getDatabaseName();

        fwrite($handle, "-- HRIS Database Backup\n");
        fwrite($handle, '-- Database: '.$database."\n");
        fwrite($handle, '-- Generated: '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET NAMES utf8mb4;\n\n");

        foreach ($this->getTableNames() as $table) {
            $this->dumpTable($handle, $pdo, $table);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
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

    private function dumpTable($handle, \PDO $pdo, string $table): void
    {
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Table structure for `{$table}`\n");
        fwrite($handle, "-- ----------------------------\n");

        $createRow = DB::selectOne("SHOW CREATE TABLE `{$table}`");
        $createSql = $createRow->{'Create Table'} ?? null;

        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        if ($createSql) {
            fwrite($handle, $createSql.";\n\n");
        }

        $total = DB::table($table)->count();
        if ($total === 0) {
            fwrite($handle, "\n");

            return;
        }

        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Records of `{$table}` ({$total} rows)\n");
        fwrite($handle, "-- ----------------------------\n");

        $orderColumn = $this->getOrderColumn($table);

        DB::table($table)->orderBy($orderColumn)->chunk(self::CHUNK_SIZE, function ($rows) use ($handle, $pdo, $table) {
            if ($rows->isEmpty()) {
                return;
            }

            $columns = array_keys((array) $rows->first());
            $columnList = implode('`, `', $columns);

            $valueGroups = [];
            foreach ($rows as $row) {
                $values = array_map(function ($value) use ($pdo) {
                    if (is_null($value)) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $value);
                }, (array) $row);

                $valueGroups[] = '('.implode(', ', $values).')';
            }

            fwrite(
                $handle,
                "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n".implode(",\n", $valueGroups).";\n"
            );
        });

        fwrite($handle, "\n");
    }
}
