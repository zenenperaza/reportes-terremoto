<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = request()->user()->load([
            'projects.donante',
            'assignedStates',
            'assignedMunicipalities.state',
        ]);

        return view('profile.show', compact('user'));
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $attributes = ['name' => $data['name']];

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        $request->user()->update($attributes);

        return redirect()->route('profile.show')->with('success', 'Su perfil fue actualizado correctamente.');
    }
}
