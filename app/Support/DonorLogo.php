<?php

namespace App\Support;

use App\Models\Donante;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Log, Storage};
use Illuminate\Validation\ValidationException;

class DonorLogo
{
    public static function validPath(?string $path): bool
    {
        return is_string($path) && preg_match('~^donor-logos/[a-zA-Z0-9]{40}\.(?:png|jpe?g|webp)$~D', $path) === 1;
    }

    public static function store(UploadedFile $file): string
    {
        $path = $file->store('donor-logos', 'local');
        if (! $path || ! self::validPath($path)) {
            throw ValidationException::withMessages(['logo' => 'No se pudo guardar el logo del donante. Intente nuevamente.']);
        }
        return $path;
    }

    public static function url(?Donante $donor): ?string
    {
        return $donor && self::validPath($donor->logo_path) ? route('donantes.logo', [
            'donante' => $donor->id, 'v' => substr(hash('sha256', $donor->logo_path), 0, 12),
        ]) : null;
    }

    public static function delete(?string $path): void
    {
        if (! self::validPath($path)) return;
        try {
            if (! Storage::disk('local')->delete($path)) Log::warning('No se pudo retirar un logo de donante del almacenamiento privado.');
        } catch (\Throwable) {
            // A failed cleanup must not turn a committed donor update into a failed response.
            Log::warning('No se pudo retirar un logo de donante del almacenamiento privado.');
        }
    }
}
