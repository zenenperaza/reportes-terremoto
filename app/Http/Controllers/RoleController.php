<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const PROTECTED_ROLES = ['admin', 'coordinator', 'reporter'];

    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->where('guard_name', 'web')->with('permissions')->withCount('users')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('roles.create', ['permissions' => $this->permissions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $role = Role::create(['name' => trim($data['name']), 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): View
    {
        abort_unless($role->guard_name === 'web', 404);

        return view('roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => $this->permissions(),
            'protectedRole' => in_array($role->name, self::PROTECTED_ROLES, true),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        $originalName = $role->name;
        $data = $this->validateData($request, $role);

        if (in_array($originalName, self::PROTECTED_ROLES, true)) {
            $data['name'] = $originalName;
        }

        $permissionIds = collect($data['permissions'] ?? []);
        if ($originalName === 'admin') {
            $adminPermission = Permission::findByName('administrar sistema', 'web');
            $permissionIds->push($adminPermission->id);
        }

        DB::transaction(function () use ($role, $originalName, $data, $permissionIds): void {
            $role->update(['name' => trim($data['name'])]);
            $role->syncPermissions($permissionIds->unique()->all());

            if ($originalName !== $role->name) {
                User::query()->where('role', $originalName)->update(['role' => $role->name]);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->with('error', 'Los roles base del sistema no se pueden eliminar.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'No puede eliminar el rol porque tiene usuarios asignados.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }

    private function validateData(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'web')],
        ]);
    }

    private function permissions()
    {
        return Permission::query()->where('guard_name', 'web')->orderBy('name')->get();
    }
}
