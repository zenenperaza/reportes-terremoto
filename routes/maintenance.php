<?php

use App\Http\Controllers\TemporaryMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::get('/ejecutar-migraciones-temp', TemporaryMaintenanceController::class)
    ->name('maintenance.refresh-cache');
