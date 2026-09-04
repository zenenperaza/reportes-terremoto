<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    private const PROTECTED_PERMISSIONS = [
        'administrar sistema',
        'coordinar registros',
        'actualizar a reportado',
        'registrar actividad',
        'manejar lugares',
        'editar registros',
        'eliminar registros',
        'solo ver registros',
        'generar respaldos',
        'descargar respaldos',
        'eliminar respaldos',
        'exportar registros excel',
        'exportar registros pdf',
        'ver detalle de registros',
    ];

    public function index(): View
    {
        return view('permissions.index', [
            'permissions' => Permission::query()->where('guard_name', 'web')->withCount('roles')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        Permission::create(['name' => trim($data['name']), 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('permissions.index')->with('success', 'Permiso creado correctamente.');
    }

    public function edit(Permission $permission): View
    {
        abort_unless($permission->guard_name === 'web', 404);

        return view('permissions.edit', [
            'permission' => $permission,
            'protectedPermission' => in_array($permission->name, self::PROTECTED_PERMISSIONS, true),
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        abort_unless($permission->guard_name === 'web', 404);

        if (in_array($permission->name, self::PROTECTED_PERMISSIONS, true)) {
            return back()->with('error', 'Los permisos base del sistema no se pueden renombrar.');
        }

        $data = $this->validateData($request, $permission);
        $permission->update(['name' => trim($data['name'])]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('permissions.index')->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        abort_unless($permission->guard_name === 'web', 404);

        if (in_array($permission->name, self::PROTECTED_PERMISSIONS, true)) {
            return back()->with('error', 'Los permisos base del sistema no se pueden eliminar.');
        }

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return back()->with('error', 'No puede eliminar el permiso porque está asignado.');
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('permissions.index')->with('success', 'Permiso eliminado correctamente.');
    }

    private function validateData(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission)],
        ]);
    }
}
