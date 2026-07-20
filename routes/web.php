<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficiaryLookupController;
use App\Http\Controllers\BeneficiaryReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/sectores/{sector}/actividades', [LocationController::class, 'activities'])->name('sectors.activities');
    Route::get('/beneficiarios/verificar-recurrencia', [BeneficiaryLookupController::class, 'recurrence'])->name('beneficiaries.recurrence');
    Route::post('/beneficiarios', [ReportController::class, 'storeBeneficiary'])->name('beneficiaries.store');
    Route::put('/beneficiarios/{beneficiary}', [ReportController::class, 'updateBeneficiary'])->name('beneficiaries.update');
    Route::delete('/beneficiarios/{beneficiary}', [ReportController::class, 'destroyBeneficiary'])->name('beneficiaries.destroy');
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
