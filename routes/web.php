<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', [AuthController::class, 'createLogin'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->name('login.store');
    Route::get('/registro', [AuthController::class, 'createRegister'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/panel', DashboardController::class)->name('dashboard');
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');

    Route::get('/ubicaciones/estados/{state}/municipios', [LocationController::class, 'municipalities'])->name('locations.municipalities');
    Route::get('/ubicaciones/municipios/{municipality}/parroquias', [LocationController::class, 'parishes'])->name('locations.parishes');
    Route::get('/sectores/{sector}/actividades', [LocationController::class, 'activities'])->name('sectors.activities');

    Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reportes/nuevo', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/reportes', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reportes/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reportes/{report}/revisar', [ReportController::class, 'review'])->name('reports.review');
    Route::get('/evidencias/{evidence}/descargar', [ReportController::class, 'downloadEvidence'])->name('evidences.download');
});
