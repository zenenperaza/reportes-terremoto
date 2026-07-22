<?php

namespace App\Services;

use App\Exceptions\ReverseGeocodingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class ReverseGeocoder
{
    /**
     * Obtiene la dirección administrativa de unas coordenadas y la conserva
     * temporalmente para no repetir solicitudes al proveedor de mapas.
     *
     * @return array<string, mixed>
     */
    public function resolve(float $latitude, float $longitude): array
    {
        $cacheKey = $this->cacheKey($latitude, $longitude);
        $address = Cache::get($cacheKey);

        if (is_array($address)) {
            return $address;
        }

        if (RateLimiter::tooManyAttempts('nominatim-reverse-geocode', 1)) {
            throw new ReverseGeocodingException(
                'Espere un momento e intente nuevamente validar las coordenadas.',
                429,
            );
        }

        RateLimiter::hit('nominatim-reverse-geocode', 1);

        try {
            $response = Http::acceptJson()
                ->withUserAgent((string) config('services.nominatim.user_agent'))
                ->timeout(8)
                ->get(rtrim((string) config('services.nominatim.url'), '/').'/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'zoom' => 18,
                ]);
        } catch (\Throwable) {
            throw new ReverseGeocodingException(
                'No fue posible validar las coordenadas en este momento. Intente nuevamente.',
            );
        }

        if (! $response->successful() || ! is_array($response->json('address'))) {
            throw new ReverseGeocodingException(
                'No fue posible confirmar la ubicación de estas coordenadas. Verifíquelas e intente nuevamente.',
                422,
            );
        }

        $address = $response->json('address');
        Cache::put($cacheKey, $address, now()->addDays(30));

        return $address;
    }

    /** @param array<string, mixed> $address */
    public function isInVenezuela(array $address): bool
    {
        return strtolower((string) ($address['country_code'] ?? '')) === 've';
    }

    private function cacheKey(float $latitude, float $longitude): string
    {
        return sprintf(
            'reverse-geocode:%0.5f:%0.5f',
            round($latitude, 5),
            round($longitude, 5),
        );
    }
}
