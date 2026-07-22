<?php

namespace App\Services;

use App\Exceptions\ReverseGeocodingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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

    /** @param array<string, mixed> $address */
    public function matchesAdministrativeLocation(array $address, string $stateName, string $municipalityName): ?bool
    {
        $stateCandidates = $this->addressCandidates($address, ['state', 'state_district', 'region']);
        $municipalityCandidates = $this->addressCandidates($address, ['municipality', 'county', 'city_district', 'city', 'town', 'district']);

        if ($stateCandidates === [] || $municipalityCandidates === []) {
            return null;
        }

        return $this->nameMatches($stateName, $stateCandidates)
            && $this->nameMatches($municipalityName, $municipalityCandidates);
    }

    /** @param array<string, mixed> $address
     *  @param array<int, string> $keys
     *  @return array<int, string>
     */
    private function addressCandidates(array $address, array $keys): array
    {
        return collect($keys)->map(fn (string $key) => $address[$key] ?? null)
            ->filter(fn (mixed $value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $this->normalizeLocationName($value))->filter()->unique()->values()->all();
    }

    /** @param array<int, string> $candidates */
    private function nameMatches(string $name, array $candidates): bool
    {
        $normalizedName = $this->normalizeLocationName($name);

        return collect($candidates)->contains(fn (string $candidate) => $candidate === $normalizedName
            || str_contains($candidate, $normalizedName)
            || str_contains($normalizedName, $candidate));
    }

    private function normalizeLocationName(string $name): string
    {
        $name = Str::ascii(Str::lower($name));
        $name = preg_replace('/\b(estado|municipio|parroquia|autonomo|autonoma|capital)\b/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', (string) $name)));
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
