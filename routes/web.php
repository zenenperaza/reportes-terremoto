<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadIndicadorServicioController;
use App\Http\Controllers\BeneficiaryLookupController;
use App\Http\Controllers\BeneficiaryReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonanteController;
use App\Http\Controllers\IndicadorController;
use App\Http\Controllers\IndicadorProyectoController;
use App\Http\Controllers\IndicadorProyectoActividadController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PlaceNameController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\SystemMaintenanceController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', [AuthController::class, 'createLogin'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware(['auth', EnsureActiveUser::class, 'system.maintenance'])->group(function (): void {
    Route::get('/panel', DashboardController::class)->name('dashboard');
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');
    Route::get('/mi-perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('admin')->prefix('usuarios')->name('users.')->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/nuevo', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/editar', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('admin')->group(function (): void {
        Route::resource('donantes', DonanteController::class)->except('show');
        Route::resource('proyectos', ProyectoController::class);
        Route::resource('indicadores', IndicadorController::class)
            ->parameters(['indicadores' => 'indicador'])->except('show');
        Route::resource('configuracion/actividades', ActividadController::class)
            ->parameters(['actividades' => 'actividad'])->names('actividades')->except('show');
        Route::resource('configuracion/servicios', ServicioController::class)
            ->parameters(['servicios' => 'servicio'])->names('servicios')->except('show');
        Route::get('configuracion/mantenimiento', [SystemMaintenanceController::class, 'index'])->name('system-maintenance.index');
        Route::put('configuracion/mantenimiento', [SystemMaintenanceController::class, 'update'])->name('system-maintenance.update');
        Route::get('proyectos/{proyecto}/indicadores', [IndicadorProyectoController::class, 'index'])
            ->name('proyectos.indicadores.index');
        Route::post('proyectos/{proyecto}/indicadores', [IndicadorProyectoController::class, 'store'])
            ->name('proyectos.indicadores.store');
        Route::get('indicadores-proyectos/{indicadorProyecto}/editar', [IndicadorProyectoController::class, 'edit'])
            ->name('indicador-proyecto.edit');
        Route::put('indicadores-proyectos/{indicadorProyecto}', [IndicadorProyectoController::class, 'update'])
            ->name('indicador-proyecto.update');
        Route::delete('indicadores-proyectos/{indicadorProyecto}', [IndicadorProyectoController::class, 'destroy'])
            ->name('indicador-proyecto.destroy');
        Route::get('indicadores-proyectos/{indicadorProyecto}/actividades', [IndicadorProyectoActividadController::class, 'index'])->name('indicador-proyecto.actividades.index');
        Route::post('indicadores-proyectos/{indicadorProyecto}/actividades', [IndicadorProyectoActividadController::class, 'store'])->name('indicador-proyecto.actividades.store');
        Route::put('actividades-indicadores/{actividadIndicador}', [IndicadorProyectoActividadController::class, 'update'])->name('actividad-indicador.update');
        Route::delete('actividades-indicadores/{actividadIndicador}', [IndicadorProyectoActividadController::class, 'destroy'])->name('actividad-indicador.destroy');
        Route::get('actividades-indicadores/{actividadIndicador}/servicios', [ActividadIndicadorServicioController::class, 'index'])->name('actividad-indicador.servicios.index');
        Route::post('actividades-indicadores/{actividadIndicador}/servicios', [ActividadIndicadorServicioController::class, 'store'])->name('actividad-indicador.servicios.store');
        Route::put('servicios-actividades/{servicioActividad}', [ActividadIndicadorServicioController::class, 'update'])->name('servicio-actividad.update');
        Route::delete('servicios-actividades/{servicioActividad}', [ActividadIndicadorServicioController::class, 'destroy'])->name('servicio-actividad.destroy');
    });

    Route::get('/ubicaciones/estados/{state}/municipios', [LocationController::class, 'municipalities'])->name('locations.municipalities');
    Route::get('/ubicaciones/municipios/{municipality}/parroquias', [LocationController::class, 'parishes'])->name('locations.parishes');
    Route::get('/ubicaciones/coordenadas', [LocationController::class, 'reverseGeocode'])->name('locations.reverse');
    Route::get('/lugares/sugerencias', [LocationController::class, 'places'])->name('locations.places');
    Route::middleware('admin')->group(function (): void {
        Route::get('/nombres-del-lugar', [PlaceNameController::class, 'index'])->name('place-names.index');
        Route::post('/nombres-del-lugar', [PlaceNameController::class, 'store'])->name('place-names.store');
        Route::get('/nombres-del-lugar/{placeName}/editar', [PlaceNameController::class, 'edit'])->name('place-names.edit');
        Route::put('/nombres-del-lugar/{placeName}', [PlaceNameController::class, 'update'])->name('place-names.update');
        Route::delete('/nombres-del-lugar/{placeName}', [PlaceNameController::class, 'destroy'])->name('place-names.destroy');
    });
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
    Route::get('/reportes/{report}/editar', [ReportController::class, 'edit'])->name('reports.edit');
    Route::put('/reportes/{report}', [ReportController::class, 'update'])->name('reports.update');
    Route::delete('/reportes/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
    Route::get('/reportes/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reportes/{report}/revisar', [ReportController::class, 'review'])->name('reports.review');
    Route::get('/evidencias/{evidence}/descargar', [ReportController::class, 'downloadEvidence'])->name('evidences.download');
});
