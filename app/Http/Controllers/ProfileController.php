<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

        if ($request->hasFile('profile_photo')) {
            $directory = public_path('uploads/profile-photos');
            File::ensureDirectoryExists($directory);

            $photo = $request->file('profile_photo');
            $filename = 'user-'.$request->user()->id.'-'.Str::uuid().'.'.$photo->extension();
            $photo->move($directory, $filename);

            if ($request->user()->profile_photo_path) {
                File::delete(public_path($request->user()->profile_photo_path));
            }

            $attributes['profile_photo_path'] = 'uploads/profile-photos/'.$filename;
        }

        $request->user()->update($attributes);

        return redirect()->route('profile.show')->with('success', 'Su perfil fue actualizado correctamente.');
    }
}
