<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficiaryLookupController;
use App\Http\Controllers\BeneficiaryReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/server-repair-temp', function () {
    $token = request('token');
    abort_unless($token === 'MiTokenPrivado123456', 403);

    $paths = [
        storage_path(),
        storage_path('app'),
        storage_path('framework'),
        storage_path('framework/cache'),
        storage_path('framework/cache/data'),
        storage_path('framework/sessions'),
        storage_path('framework/views'),
        storage_path('logs'),
        base_path('bootstrap'),
        base_path('bootstrap/cache'),
    ];

    $out = [];

    foreach ($paths as $path) {
        if (!file_exists($path)) {
            if (@mkdir($path, 0775, true)) {
                $out[] = "✅ Creada: {$path}";
            } else {
                $out[] = "❌ No se pudo crear: {$path}";
                continue;
            }
        } else {
            $out[] = "✔ Ya existe: {$path}";
        }

        if (@chmod($path, 0775)) {
            $out[] = "✔ Permisos aplicados: {$path}";
        } else {
            $out[] = "⚠ No se pudieron cambiar permisos: {$path}";
        }
    }

    try {
        Artisan::call('optimize:clear');
        $out[] = "✅ optimize:clear ejecutado";
        $out[] = trim(Artisan::output());
    } catch (\Throwable $e) {
        $out[] = "❌ Error en optimize:clear: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $out) . '</pre>';
});

Route::get('/server-storage-link-temp', function () {
    $token = request('token');
    abort_unless($token === 'MiTokenPrivado123456', 403);

    $out = [];
    $publicStorage = public_path('storage');

    try {
        if (is_link($publicStorage)) {
            if (@unlink($publicStorage)) {
                $out[] = "✅ Symlink anterior eliminado: {$publicStorage}";
            } else {
                $out[] = "⚠ No se pudo eliminar el symlink anterior: {$publicStorage}";
            }
        } elseif (file_exists($publicStorage)) {
            $out[] = "⚠ public/storage existe pero no es symlink. No se eliminó automáticamente.";
        }

        Artisan::call('storage:link');
        $out[] = "✅ storage:link ejecutado";
        $out[] = trim(Artisan::output());

    } catch (\Throwable $e) {
        $out[] = "❌ Error al crear el enlace simbólico: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $out) . '</pre>';
});

Route::get('/server-clear-temp', function () {
    $token = request('token');
    abort_unless($token === 'MiTokenPrivado123456', 403);

    $out = [];

    try {
        Artisan::call('optimize:clear');
        $out[] = "✅ optimize:clear ejecutado";
        $out[] = trim(Artisan::output());
    } catch (\Throwable $e) {
        $out[] = "❌ Error en optimize:clear: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $out) . '</pre>';
});

Route::get('/server-migrate-temp', function () {
    $token = request('token');
    abort_unless($token === 'MiTokenPrivado123456', 403);

    $out = [];

    try {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
        $out[] = "✅ migrate ejecutado";
        $out[] = trim(Artisan::output());

        Artisan::call('db:seed', [
            '--force' => true,
        ]);
        $out[] = "✅ db:seed ejecutado";
        $out[] = trim(Artisan::output());

    } catch (\Throwable $e) {
        $out[] = "❌ Error en migraciones/seeders: " . $e->getMessage();
    }

    return '<pre>' . implode("\n\n", $out) . '</pre>';
});

Route::get('/server-migrate-fresh-temp', function () {
    $token = request('token');
    abort_unless($token === 'MiTokenPrivado123456', 403);

    $out = [];

    try {
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        $out[] = "✅ migrate:fresh --seed ejecutado";
        $out[] = trim(Artisan::output());

    } catch (\Throwable $e) {
        $out[] = "❌ Error en migrate:fresh: " . $e->getMessage();
    }

    return '<pre>' . implode("\n\n", $out) . '</pre>';
});


Route::redirect('/', '/panel');

Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', [AuthController::class, 'createLogin'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/panel', DashboardController::class)->name('dashboard');
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->prefix('usuarios')->name('users.')->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/nuevo', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/editar', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
    });

    Route::get('/ubicaciones/estados/{state}/municipios', [LocationController::class, 'municipalities'])->name('locations.municipalities');
    Route::get('/ubicaciones/municipios/{municipality}/parroquias', [LocationController::class, 'parishes'])->name('locations.parishes');
    Route::get('/lugares/sugerencias', [LocationController::class, 'places'])->name('locations.places');
    Route::get('/actividades', [LocationController::class, 'allActivities'])->name('activities.all');
    Route::get('/sectores/{sector}/actividades', [LocationController::class, 'activities'])->name('sectors.activities');
    Route::get('/beneficiarios/verificar-recurrencia', [BeneficiaryLookupController::class, 'recurrence'])->name('beneficiaries.recurrence');
    Route::post('/beneficiarios', [ReportController::class, 'storeBeneficiary'])->name('beneficiaries.store');
    Route::put('/beneficiarios/{beneficiary}', [ReportController::class, 'updateBeneficiary'])->name('beneficiaries.update');
    Route::delete('/beneficiarios/{beneficiary}', [ReportController::class, 'destroyBeneficiary'])->name('beneficiaries.destroy');
    Route::get('/informe-beneficiarios/exportar', [BeneficiaryReportController::class, 'export'])->name('beneficiaries.export');
    Route::get('/informe-beneficiarios', [BeneficiaryReportController::class, 'index'])->name('beneficiaries.summary');
    Route::post('/informe-beneficiarios/marcar-reportados', [BeneficiaryReportController::class, 'markAsReported'])->name('beneficiaries.mark-reported');

    Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reportes/nuevo', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/reportes', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reportes/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reportes/{report}/revisar', [ReportController::class, 'review'])->name('reports.review');
    Route::get('/evidencias/{evidence}/descargar', [ReportController::class, 'downloadEvidence'])->name('evidences.download');
});
