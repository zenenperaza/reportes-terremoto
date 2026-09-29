<?php

// Regression check on connection-local TEMPORARY tables only.
// Temporary tables shadow application tables; no persistent records are written.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$connection = DB::connection();
if (!in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
    || !in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Solo se permite MySQL local.');
}
$pdo = $connection->getPdo();
$sql = file_get_contents(__DIR__.'/../docs/sql/asignar_periodos_historicos_20260929.sql');
$split = fn ($text) => array_filter(array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $text))));
$run = function (string $statement) use ($pdo): ?array {
    if (preg_match('/^SELECT\b/i', $statement)) {
        $query = $pdo->query($statement);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $query->closeCursor();
        return $rows;
    }
    $pdo->exec($statement);
    return null;
};
$assert = function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $pdo->exec("CREATE TEMPORARY TABLE reports (
        id BIGINT UNSIGNED PRIMARY KEY, report_date DATE NOT NULL,
        created_at DATETIME NULL, updated_at DATETIME NULL, reporting_period CHAR(7) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TEMPORARY TABLE reporting_periods (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, period CHAR(7) UNIQUE NOT NULL,
        is_closed TINYINT NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TEMPORARY TABLE sia_respaldo_periodos_20260929 (
        report_id BIGINT UNSIGNED PRIMARY KEY, fecha_atencion DATE NOT NULL,
        registro_creado DATETIME NULL, registro_actualizado DATETIME NULL,
        periodo_anterior CHAR(7) NULL, periodo_nuevo CHAR(7) NOT NULL, respaldado_en DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("CREATE TEMPORARY TABLE tmp_sia_periodos_20260929 (
        report_id BIGINT UNSIGNED PRIMARY KEY, fecha_atencion DATE NOT NULL,
        registro_creado DATETIME NULL, registro_actualizado DATETIME NULL, periodo_nuevo CHAR(7) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $initialized = false;
    foreach ([763, 0] as $expectedChanges) {
        $summaries = [];
        foreach ($split($sql) as $statement) {
            // The persistent backup DDL is replaced by the already-created temporary fixture.
            if (str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS sia_respaldo_periodos_20260929')) continue;
            if (!$initialized && strtoupper($statement) === 'START TRANSACTION') {
                $pdo->exec('INSERT INTO reports SELECT report_id, fecha_atencion, registro_creado, registro_actualizado, NULL FROM tmp_sia_periodos_20260929');
                $pdo->exec("INSERT INTO reports VALUES (900001,'2026-09-29',NULL,NULL,'2026-09'),(900002,'2026-09-29',NULL,NULL,'2026-09')");
                $pdo->exec("INSERT INTO reporting_periods (period, is_closed) VALUES ('2026-09',0),('2026-10',1)");
                $reproduced = false;
                try {
                    $run('SELECT COUNT(*) FROM reports r JOIN tmp_sia_periodos_20260929 t ON r.reporting_period = t.periodo_nuevo');
                } catch (PDOException $error) {
                    $reproduced = ($error->errorInfo[1] ?? null) === 1267;
                }
                $assert($reproduced, 'No se reprodujo el error original #1267.');
                $initialized = true;
            }
            $result = $run($statement);
            if ($result && isset($result[0]['objetivos_debe_ser_763'])) $summaries[] = $result[0];
        }
        $summary = $summaries[0] ?? [];
        $assert((int) ($summary['actualizados_en_esta_ejecucion'] ?? -1) === $expectedChanges, 'Cantidad de actualizaciones incorrecta.');
        $assert((int) ($summary['objetivos_con_periodo_correcto_debe_ser_763'] ?? -1) === 763, 'Resumen final incorrecto.');
        echo json_encode(['temporary_fixture' => true, 'original_1267_reproduced' => true, 'summary' => $summary], JSON_THROW_ON_ERROR).PHP_EOL;
    }

    foreach ($split(file_get_contents(__DIR__.'/../docs/sql/verificar_periodos_historicos_20260929.sql')) as $statement) {
        $rows = $run($statement);
        if (isset($rows[0]['respaldados_con_periodo_correcto_debe_ser_763'])) {
            $assert((int) $rows[0]['respaldados_con_periodo_correcto_debe_ser_763'] === 763, 'Verificacion independiente incorrecta.');
        }
    }
    echo "Verificacion de solo lectura correcta. Ninguna tabla persistente modificada.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
