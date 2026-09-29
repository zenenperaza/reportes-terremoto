<?php

// Executes the exact phpMyAdmin artifact, on the verified local database only.
// Default: validate in a transaction and roll back. --apply commits after checks.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function fingerprint(string $table, array $omit = []): string
{
    $context = hash_init('sha256');
    foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
        $data = (array) $row;
        foreach ($omit as $column) unset($data[$column]);
        hash_update($context, json_encode($data, JSON_THROW_ON_ERROR));
    }
    return hash_final($context);
}

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) throw new RuntimeException('Use --check o --apply.');
$connection = DB::connection();
if (!in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
    || !in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
    || $connection->getDatabaseName() !== 'asonacop_registros') {
    throw new RuntimeException('Solo se permite asonacop_registros en MySQL local.');
}
$pdo = $connection->getPdo();
$backup = 'sia_respaldo_periodos_20260929';
$sql = file_get_contents(__DIR__.'/../docs/sql/asignar_periodos_historicos_20260929.sql');
$statements = array_filter(array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $sql))));
$before = [
    'reports_except_period' => fingerprint('reports', ['reporting_period']),
    'beneficiaries' => fingerprint('beneficiaries'),
    'settings' => fingerprint('system_settings'),
];
$originalPeriods = DB::table('reports')->pluck('reporting_period', 'id')->all();
$originalCatalog = DB::table('reporting_periods')->orderBy('id')->get()->keyBy('id');
$originalBackup = Schema::hasTable($backup) ? DB::table($backup)->get()->keyBy('report_id') : collect();
$verified = false;

try {
    foreach ($statements as $statement) {
        if (strtoupper($statement) === 'COMMIT') {
            $targets = DB::table('tmp_sia_periodos_20260929')->get()->keyBy('report_id');
            if ($targets->count() !== 763) throw new RuntimeException('Cantidad de objetivos inesperada.');
            $changed = 0;
            foreach (DB::table('reports')->orderBy('id')->cursor() as $report) {
                $target = $targets->get($report->id);
                $expected = $target?->periodo_nuevo ?? $originalPeriods[$report->id];
                if ($report->reporting_period !== $expected) throw new RuntimeException('Un objetivo fue omitido o un registro no previsto cambio.');
                if ($report->reporting_period !== $originalPeriods[$report->id]) $changed++;
            }
            if ($before['reports_except_period'] !== fingerprint('reports', ['reporting_period'])
                || $before['beneficiaries'] !== fingerprint('beneficiaries')
                || $before['settings'] !== fingerprint('system_settings')) {
                throw new RuntimeException('Se detectaron cambios fuera del campo periodo.');
            }
            foreach ($originalCatalog as $id => $period) {
                if ((array) $period !== (array) DB::table('reporting_periods')->where('id', $id)->first()) {
                    throw new RuntimeException('Cambio no previsto en un periodo existente.');
                }
            }
            foreach ($originalBackup as $id => $entry) {
                if ((array) $entry !== (array) DB::table($backup)->where('report_id', $id)->first()) {
                    throw new RuntimeException('Se altero un respaldo anterior.');
                }
            }
            if (DB::table($backup)->whereIn('report_id', $targets->keys())->count() !== 763) {
                throw new RuntimeException('El respaldo esta incompleto.');
            }
            $counts = DB::table('reports')->selectRaw('reporting_period, COUNT(*) AS reports')
                ->groupBy('reporting_period')->orderBy('reporting_period')->get();
            $pdo->exec($mode === '--apply' ? 'COMMIT' : 'ROLLBACK');
            $verified = true;
            echo json_encode([
                'mode' => $mode, 'committed' => $mode === '--apply', 'changed' => $changed,
                'snapshot_targets' => 763, 'backed_up' => 763,
                'reports_except_period_unchanged' => true, 'beneficiaries_unchanged' => true,
                'existing_periods_and_settings_unchanged' => true, 'result_counts' => $counts,
                'sql_sha256' => hash('sha256', $sql),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
            continue;
        }
        if (preg_match('/^SELECT\b/i', $statement)) {
            $result = $pdo->query($statement);
            $result->fetchAll();
            $result->closeCursor();
        } else {
            $pdo->exec($statement);
        }
    }
    if (!$verified) throw new RuntimeException('No se encontro el punto de verificacion.');
} catch (Throwable $error) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $rollbackError) {
        fwrite(STDERR, 'No se pudo confirmar ROLLBACK: '.$rollbackError->getMessage().PHP_EOL);
    }
    fwrite(STDERR, 'ERROR: '.$error->getMessage().PHP_EOL);
    exit(1);
}
