<?php

// Read-only aggregate audit. Never emits beneficiary names or credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$connection = DB::connection();
if (!in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
    || !in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Se requiere la base MySQL local.');
}
$bucket = "CASE WHEN report_date >= '2026-06-01' AND report_date < '2026-07-28' THEN '2026-07'
    WHEN report_date >= '2026-07-28' AND report_date < '2026-09-01' THEN '2026-08'
    WHEN report_date >= '2026-09-01' THEN '2026-09' ELSE 'fuera_de_rango' END";
if (in_array('--mapping', $argv, true)) {
    echo DB::table('reports')->whereNull('reporting_period')->where('report_date', '>=', '2026-06-01')
        ->orderBy('id')->selectRaw("id, report_date, created_at, updated_at, $bucket AS proposed_period")->get()->toJson(JSON_THROW_ON_ERROR);
    exit;
}
echo json_encode([
    'database' => $connection->getDatabaseName(),
    'version' => DB::selectOne('SELECT VERSION() AS version')->version,
    'reports' => DB::table('reports')->count(),
    'beneficiaries' => DB::table('beneficiaries')->count(),
    'date_bounds' => DB::table('reports')->selectRaw('MIN(report_date) AS first_date, MAX(report_date) AS last_date')->first(),
    'proposed_groups' => DB::table('reports')->selectRaw("$bucket AS proposed_period, reporting_period AS current_period, COUNT(*) AS reports")
        ->groupBy('proposed_period', 'reporting_period')->orderBy('proposed_period')->get(),
    'period_states' => DB::table('reporting_periods')->select('period', 'is_closed')->orderBy('period')->get(),
    'engines' => DB::select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('reports', 'beneficiaries', 'reporting_periods')"),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
