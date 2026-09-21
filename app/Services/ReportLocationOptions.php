<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;

class ReportLocationOptions
{
    /** Locations with saved reports in the caller's authorized reporting scope. */
    public function get(Builder $reports, array $stateIds = [], ?int $municipalityId = null): array
    {
        $states = State::query()
            ->whereIn('id', (clone $reports)->select('state_id'))
            ->orderBy('name')->get(['id', 'name']);

        $inStates = (clone $reports)
            ->when($stateIds, fn (Builder $query) => $query->whereIn('state_id', $stateIds));
        $municipalities = Municipality::query()->with('state:id,name')
            ->whereIn('id', (clone $inStates)->select('municipality_id'))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'state_id']);

        $parishes = Parish::query()->with('municipality:id,name,state_id', 'municipality.state:id,name')
            ->whereIn('id', (clone $inStates)
                ->when($municipalityId, fn (Builder $query) => $query->where('municipality_id', $municipalityId))
                ->select('parish_id'))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'municipality_id']);

        return compact('states', 'municipalities', 'parishes');
    }
}
