<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use Illuminate\Http\Request;

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
}
