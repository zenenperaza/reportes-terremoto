<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->withCount('reports')->orderBy('name')->paginate(20),
            'roleLabels' => User::roleLabels(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['roleLabels' => User::roleLabels()]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()->route('users.edit', $user)->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'managedUser' => $user,
            'roleLabels' => User::roleLabels(),
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($this->wouldRemoveLastAdministrator($user, $data['role'])) {
            return back()->withInput()->with('error', 'Debe conservar al menos una cuenta administradora.');
        }

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->withInput()->with('error', 'No puede quitar el rol administrador de su propia cuenta.');
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puede eliminar su propia cuenta.');
        }

        if ($user->isAdministrator() && User::query()->where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Debe conservar al menos una cuenta administradora.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente. Sus registros históricos se conservan.');
    }

    private function wouldRemoveLastAdministrator(User $user, string $newRole): bool
    {
        return $user->isAdministrator()
            && $newRole !== 'admin'
            && User::query()->where('role', 'admin')->count() <= 1;
    }
}
