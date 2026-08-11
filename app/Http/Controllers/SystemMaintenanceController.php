<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemMaintenanceController extends Controller
{
    public function index(): View
    {
        return view('maintenance.index', ['maintenanceEnabled' => SystemSetting::maintenanceEnabled()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        SystemSetting::query()->updateOrCreate(
            ['key' => SystemSetting::MAINTENANCE_MODE],
            ['value' => $data['enabled'] ? '1' : '0', 'updated_by' => $request->user()->id]
        );

        return redirect()->route('system-maintenance.index')->with(
            'success',
            $data['enabled']
                ? 'El sistema fue bloqueado para registradores y coordinadores.'
                : 'El sistema fue habilitado nuevamente para todos los usuarios.'
        );
    }
}
