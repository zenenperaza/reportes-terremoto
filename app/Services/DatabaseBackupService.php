<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    public const DIRECTORY = 'backups';

    public function create(): string
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('La creación de respaldos está disponible únicamente para bases de datos MySQL/MariaDB.');
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);
        $filename = 'asonacop-'.now()->format('Y-m-d_H-i-s').'-'.bin2hex(random_bytes(3)).'.sql.gz';
        $temporary = self::DIRECTORY.'/'.$filename.'.part';
        $relativePath = self::DIRECTORY.'/'.$filename;
        $stream = gzopen($disk->path($temporary), 'wb9');

        if ($stream === false) {
            throw new RuntimeException('No fue posible crear el archivo de respaldo. Verifique los permisos de storage.');
        }

        try {
            $this->write($stream, "-- Respaldo de base de datos ASONACOP\n");
            $this->write($stream, '-- Generado: '.now()->toDateTimeString()."\n");
            $this->write($stream, '-- Base de datos: '.config('database.connections.mysql.database')."\n\n");
            $this->write($stream, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($this->tables() as $table) {
                $this->dumpTable($stream, $table);
            }

            $this->write($stream, "SET FOREIGN_KEY_CHECKS=1;\n");
            gzclose($stream);
            $stream = null;

            if (! rename($disk->path($temporary), $disk->path($relativePath))) {
                throw new RuntimeException('No fue posible finalizar el archivo de respaldo.');
            }

            return $filename;
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                gzclose($stream);
            }
            $disk->delete([$temporary, $relativePath]);
            throw $exception;
        }
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        return collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn (object $row): string => (string) array_values((array) $row)[0])
            ->sort()
            ->values()
            ->all();
    }

    private function dumpTable($stream, string $table): void
    {
        $quotedTable = $this->identifier($table);
        $definition = DB::selectOne("SHOW CREATE TABLE {$quotedTable}");
        $createSql = array_values((array) $definition)[1] ?? null;

        if (! is_string($createSql)) {
            throw new RuntimeException("No se pudo obtener la estructura de la tabla {$table}.");
        }

        $this->write($stream, "-- --------------------------------------------------------\n");
        $this->write($stream, "-- Tabla: {$table}\n\nDROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

        $batch = [];
        foreach (DB::table($table)->cursor() as $row) {
            $values = (array) $row;
            $columns = array_map(fn (string $column): string => $this->identifier($column), array_keys($values));
            $batch[] = '('.implode(', ', array_map(fn (mixed $value): string => $this->value($value), array_values($values))).')';

            if (count($batch) === 200) {
                $this->writeInsert($stream, $quotedTable, $columns, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->writeInsert($stream, $quotedTable, $columns, $batch);
        }

        $this->write($stream, "\n");
    }

    /** @param array<int, string> $columns @param array<int, string> $rows */
    private function writeInsert($stream, string $table, array $columns, array $rows): void
    {
        $this->write($stream, "INSERT INTO {$table} (".implode(', ', $columns).") VALUES\n".implode(",\n", $rows).";\n");
    }

    private function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function value(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    private function write($stream, string $contents): void
    {
        if (gzwrite($stream, $contents) === false) {
            throw new RuntimeException('No fue posible escribir el respaldo en el almacenamiento privado.');
        }
    }
}
