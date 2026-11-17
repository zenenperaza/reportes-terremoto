<?php

namespace App\Http\Controllers;

use App\Models\Donante;
use App\Support\DonorLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DonorLogoController extends Controller
{
    public function show(Request $request, Donante $donante)
    {
        $user = $request->user();
        abort_unless($user->isAdministrator() || ($user->can('ver buzon') && $user->can('crear buzon')
            && $donante->proyectos()->whereHas('users', fn ($users) => $users->whereKey($user->id))->exists()), 404);
        abort_unless(DonorLogo::validPath($donante->logo_path) && Storage::disk('local')->exists($donante->logo_path), 404);
        $mime = Storage::disk('local')->mimeType($donante->logo_path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return Storage::disk('local')->response($donante->logo_path, 'logo-donante.'.pathinfo($donante->logo_path, PATHINFO_EXTENSION), [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'", 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
