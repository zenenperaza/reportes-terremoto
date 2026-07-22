<?php

namespace App\Http\Controllers;

use App\Exceptions\ReverseGeocodingException;
use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Services\ReverseGeocoder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    public function municipalities(State $state)
    {
        return $state->municipalities()->orderBy('name')->get(['id', 'name']);
    }

    public function parishes(Municipality $municipality)
    {
        return $municipality->parishes()->orderBy('name')->get(['id', 'name']);
    }

    public function activities(Sector $sector)
    {
        return $sector->activities()->where('active', true)->orderBy('sort_order')->get(['id', 'code', 'title']);
    }

    public function allActivities()
    {
        return Activity::query()
            ->where('active', true)
            ->orderBy('sector_id')
            ->orderBy('sort_order')
            ->get(['id', 'code', 'title']);
    }

    public function places(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id'],
            'installation_type' => ['nullable', 'string', 'max:150'],
        ]);

        $places = Report::query()
            ->whereNotNull('place_name')
            ->where('place_name', '!=', '');

        if (! $request->user()->isCoordinator()) {
            $places->where('user_id', $request->user()->id);
        }

        return $places
            ->when($filters['state_id'] ?? null, fn ($query, int $stateId) => $query->where('state_id', $stateId))
            ->when($filters['municipality_id'] ?? null, fn ($query, int $municipalityId) => $query->where('municipality_id', $municipalityId))
            ->when($filters['parish_id'] ?? null, fn ($query, int $parishId) => $query->where('parish_id', $parishId))
            ->when($filters['installation_type'] ?? null, fn ($query, string $installationType) => $query->where('installation_type', $installationType))
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where('place_name', 'like', "%{$term}%"))
            ->select('place_name')
            ->distinct()
            ->orderBy('place_name')
            ->limit(12)
            ->pluck('place_name')
            ->values();
    }

    public function reverseGeocode(Request $request, ReverseGeocoder $reverseGeocoder)
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $address = $reverseGeocoder->resolve(
                (float) $coordinates['latitude'],
                (float) $coordinates['longitude'],
            );
        } catch (ReverseGeocodingException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        if (! $reverseGeocoder->isInVenezuela($address)) {
            return response()->json([
                'message' => 'Las coordenadas no corresponden al territorio venezolano. Verifique la latitud y longitud.',
            ], 422);
        }

        $location = $this->matchAdministrativeLocation($address);
        $matchedNames = collect($location)
            ->filter()
            ->pluck('name')
            ->implode(', ');
        $isComplete = collect($location)->filter()->count() === 3;

        return response()->json([
            'location' => $location,
            'message' => match (true) {
                $isComplete => "Ubicación identificada: {$matchedNames}.",
                $matchedNames !== '' => "Ubicación identificada parcialmente: {$matchedNames}. Complete los campos restantes manualmente.",
                default => 'Las coordenadas se agregaron. Seleccione Estado, Municipio y Parroquia manualmente.',
            },
        ]);
    }

    /** @param array<string, mixed> $address */
    private function matchAdministrativeLocation(array $address): array
    {
        $state = $this->matchLocation(
            State::query(),
            $this->addressCandidates($address, ['state', 'state_district', 'region']),
        );

        $municipality = $state
            ? $this->matchLocation(
                $state->municipalities(),
                $this->addressCandidates($address, ['municipality', 'county', 'city_district', 'city', 'town', 'district']),
            )
            : null;

        $parish = $municipality
            ? $this->matchLocation(
                $municipality->parishes(),
                $this->addressCandidates($address, ['suburb', 'village', 'neighbourhood', 'city_district', 'district', 'hamlet']),
            )
            : null;

        return [
            'state' => $state ? $state->only(['id', 'name']) : null,
            'municipality' => $municipality ? $municipality->only(['id', 'name']) : null,
            'parish' => $parish ? $parish->only(['id', 'name']) : null,
        ];
    }

    /** @param array<string, mixed> $address
     *  @param array<int, string> $keys
     *  @return array<int, string>
     */
    private function addressCandidates(array $address, array $keys): array
    {
        return collect($keys)
            ->map(fn (string $key) => $address[$key] ?? null)
            ->filter(fn (mixed $value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    /** @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $query
     *  @param array<int, string> $candidates
     */
    private function matchLocation($query, array $candidates): ?object
    {
        $normalizedCandidates = collect($candidates)
            ->map(fn (string $candidate) => $this->normalizeLocationName($candidate))
            ->filter()
            ->values();

        if ($normalizedCandidates->isEmpty()) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($query->orderBy('name')->get(['id', 'name']) as $location) {
            $normalizedName = $this->normalizeLocationName($location->name);

            foreach ($normalizedCandidates as $candidate) {
                $score = match (true) {
                    $normalizedName === $candidate => 100,
                    str_contains($normalizedName, $candidate) || str_contains($candidate, $normalizedName) => 85,
                    default => 0,
                };

                if ($score > $bestScore) {
                    $bestMatch = $location;
                    $bestScore = $score;
                }
            }
        }

        return $bestScore >= 85 ? $bestMatch : null;
    }

    private function normalizeLocationName(string $name): string
    {
        $name = Str::ascii(Str::lower($name));
        $name = preg_replace('/\b(estado|municipio|parroquia|autonomo|autonoma|capital)\b/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', (string) $name)));
    }
}
