<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\UserPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SystemMaintenanceController extends Controller
{
    public function index(): View
    {
        $state = SystemSetting::maintenanceState();

        return view('maintenance.index', [
            'maintenanceEnabled' => $state['enabled'], 'maintenanceState' => $state,
            'connectedUsers' => UserPresence::connectedCount(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'delay_minutes' => ['nullable', 'integer', Rule::in([0, 5, 10])],
            'message' => ['nullable', 'string', 'max:300'],
        ]);
        $delay = $data['enabled'] ? (int) ($data['delay_minutes'] ?? 0) : 0;
        DB::transaction(function () use ($data, $delay, $request): void {
            // Serialize administrators' changes; mode and deadline are a single state.
            SystemSetting::firstOrCreate(['key' => SystemSetting::MAINTENANCE_MODE], ['value' => '0']);
            SystemSetting::where('key', SystemSetting::MAINTENANCE_MODE)->lockForUpdate()->first();
            if ($delay && SystemSetting::maintenanceEnabled()) {
                throw ValidationException::withMessages(['delay_minutes' => 'Habilite el sistema antes de programar otro bloqueo.']);
            }
            $schedule = $delay ? json_encode([
                'starts_at' => now()->addMinutes($delay)->utc()->toIso8601String(),
                'message' => $data['message'] ?? 'Se realizarán modificaciones en el sistema. Guarde su trabajo antes del bloqueo.',
            ], JSON_THROW_ON_ERROR) : null;
            SystemSetting::updateOrCreate(['key' => SystemSetting::MAINTENANCE_MODE], [
                'value' => $data['enabled'] && ! $delay ? '1' : '0', 'updated_by' => $request->user()->id,
            ]);
            SystemSetting::updateOrCreate(['key' => SystemSetting::MAINTENANCE_SCHEDULE], [
                'value' => $schedule, 'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('system-maintenance.index')->with(
            'success',
            $delay ? 'Bloqueo programado dentro de '.$delay.' minutos. Los usuarios recibirán un aviso en sus pantallas.' : ($data['enabled']
                ? 'El sistema fue bloqueado para registradores y coordinadores.'
                : 'El sistema fue habilitado y se canceló cualquier bloqueo programado.')
        );
    }

    public function status(Request $request): JsonResponse
    {
        UserPresence::heartbeat($request);
        $payload = SystemSetting::maintenanceState() + [
            'server_now' => now()->utc()->toIso8601String(),
            'administrator' => $request->user()->isAdministrator(),
            'connected_users' => UserPresence::connectedCount(),
        ];

        return response()->json($payload + \App\Support\SystemNotifications::pending($request->user()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function readNotifications(Request $request): \Illuminate\Http\Response
    {
        abort_unless(\App\Support\SystemNotifications::allowed($request->user()), 403);
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['required', 'integer', 'min:1']]);
        if (\Illuminate\Support\Facades\Schema::hasTable('system_notifications')) {
            \App\Models\SystemNotification::query()->where('user_id', $request->user()->id)
                ->whereIn('id', $data['ids'])->whereNull('read_at')->update(['read_at' => now()]);
        }

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }
}
