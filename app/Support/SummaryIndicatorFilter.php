<?php

namespace App\Support;

use App\Models\Report;
use App\Models\SummaryIndicator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SummaryIndicatorFilter
{
    public static function options(User $user): Collection
    {
        return SummaryIndicator::with('proyecto:id,codigo,nombre_alias')->where('is_active', true)->whereNotNull('proyecto_id')
            ->when(! $user->isAdministrator(), fn (Builder $query) => $query->whereHas('assignments',
                fn (Builder $assignments) => $assignments->whereColumn('indicador_proyecto.proyecto_id', 'summary_indicators.proyecto_id')->whereIn('indicador_proyecto.id',
                    $user->constrainVisibleReports(Report::query())->select('reports.indicador_proyecto_id'))))
            ->orderBy('code')->get(['id', 'proyecto_id', 'code', 'name']);
    }

    public static function selection(Request $request): array
    {
        $ids = $request->input('summary_indicator_ids') ?? [];
        if ($ids === '') $ids = [];
        if ($ids === []) return [];
        $data = validator(['summary_indicator_ids' => $ids], [
            'summary_indicator_ids' => ['array', 'max:100'],
            'summary_indicator_ids.*' => ['required', 'integer', 'distinct', Rule::in(self::options($request->user())->pluck('id')->all())],
        ], ['summary_indicator_ids.*.in' => 'Seleccione un Indicador Resumen activo y disponible para su usuario.'])->validate();
        return array_map('intval', $data['summary_indicator_ids']);
    }

    public static function apply(Builder $reports, array $ids): Builder
    {
        if ($ids === []) return $reports;
        // A subquery is a set: overlapping summaries never multiply report rows.
        return $reports->whereIn('reports.indicador_proyecto_id', DB::table('summary_indicator_assignments as summary_members')
            ->join('summary_indicators as summaries', 'summaries.id', '=', 'summary_members.summary_indicator_id')
            ->join('indicador_proyecto as member_assignments', 'member_assignments.id', '=', 'summary_members.indicador_proyecto_id')
            ->whereIn('summaries.id', $ids)->where('summaries.is_active', true)
            ->whereColumn('summaries.proyecto_id', 'member_assignments.proyecto_id')
            ->whereColumn('member_assignments.proyecto_id', 'reports.proyecto_id')
            ->select('member_assignments.id'));
    }

    public static function labels(array $ids): string
    {
        return $ids === [] ? 'Todos (sin filtro)' : SummaryIndicator::with('proyecto')->whereIn('id', $ids)->orderBy('code')->get()
            ->map(fn ($summary) => ($summary->proyecto?->codigo ?? 'Proyecto pendiente').' / '.$summary->code.' — '.$summary->name)->implode(', ');
    }
}
