<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserGroupController extends Controller
{
    public function index(): View
    {
        return view('user-groups.index', [
            'groups' => UserGroup::query()->withCount('users')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('user-groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        UserGroup::create($this->validated($request));

        return redirect()->route('user-groups.index')->with('success', 'Grupo de usuarios creado correctamente.');
    }

    public function edit(UserGroup $userGroup): View
    {
        $userGroup->load(['users' => fn ($query) => $query->orderBy('name')]);

        return view('user-groups.edit', compact('userGroup'));
    }

    public function update(Request $request, UserGroup $userGroup): RedirectResponse
    {
        $userGroup->update($this->validated($request, $userGroup));

        return redirect()->route('user-groups.index')->with('success', 'Grupo de usuarios actualizado correctamente.');
    }

    public function destroy(UserGroup $userGroup): RedirectResponse
    {
        if ($userGroup->users()->exists()) {
            return back()->with('error', 'No puede eliminar el grupo mientras tenga usuarios asignados.');
        }

        $userGroup->delete();

        return redirect()->route('user-groups.index')->with('success', 'Grupo de usuarios eliminado correctamente.');
    }

    private function validated(Request $request, ?UserGroup $userGroup = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('user_groups', 'name')->ignore($userGroup)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
