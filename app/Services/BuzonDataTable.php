<?php

namespace App\Services;

use App\Models\{BuzonEntry, BuzonList, Proyecto};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BuzonDataTable
{
    /** The controller supplies the same authorized, filtered query as the normal page. */
    public function response(Request $request, Builder $query): JsonResponse
    {
        $input = $request->validate([
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:200'],
            'order' => ['nullable', 'array', 'max:1'],
            'order.*.column' => ['required', 'integer', 'between:0,9'],
            'order.*.dir' => ['required', Rule::in(['asc', 'desc'])],
            'export_type' => ['nullable', Rule::in(['excel', 'pdf'])],
        ]);
        $export = filled($input['export_type'] ?? null);
        abort_if($export && ! $request->user()->can('exportar buzon'), 403);
        $total = (clone $query)->count();
        $search = trim($input['search']['value'] ?? '');
        if ($search !== '') $this->search($query, $search);
        $filtered = (clone $query)->count();
        $direction = $input['order'][0]['dir'] ?? 'desc';
        $column = (int) ($input['order'][0]['column'] ?? 0);
        if ($column === 1) {
            $query->orderBy(Proyecto::select('codigo')->whereColumn('proyectos.id', 'buzon_entries.proyecto_id'), $direction);
        } elseif (in_array($column, [3, 4, 8], true)) {
            $field = [3 => 'opinion', 4 => 'feedback_type', 8 => 'age_group'][$column];
            $labels = [3 => BuzonEntry::OPINIONS, 4 => BuzonEntry::FEEDBACK_TYPES, 8 => BuzonEntry::AGE_GROUPS][$column];
            $cases = implode(' ', array_fill(0, count($labels), 'WHEN ? THEN ?'));
            $bindings = [];
            foreach ($labels as $value => $label) { $bindings[] = $value; $bindings[] = $label; }
            $query->orderByRaw('CASE '.$field.' '.$cases.' ELSE ? END '.$direction, [...$bindings, 'No indicado']);
        } else {
            $columns = [0 => 'opinion_date', 2 => 'place_name', 3 => 'opinion', 4 => 'feedback_type', 5 => 'full_name', 7 => 'submitted_via_public_link'];
            $query->orderBy($columns[$column] ?? 'opinion_date', $direction);
        }
        $query->orderByDesc('buzon_entries.id');
        if (! $export) $query->skip($input['start'] ?? 0)->take($input['length'] ?? 20);
        $entries = $query->with(['proyecto', 'creator', 'publicRecipient'])->get();
        $manageableIds = ! $export && ($request->user()->can('editar buzon') || $request->user()->can('eliminar buzon'))
            ? BuzonEntry::manageableBy($request->user())->whereKey($entries->modelKeys())->pluck('id')->all() : [];
        $visibleListIds = ! $export ? BuzonList::visibleTo($request->user())
            ->whereIn('id', $entries->pluck('buzon_list_id')->filter())->pluck('id')->all() : [];

        $data = $entries->map(function (BuzonEntry $entry) use ($export, $manageableIds, $visibleListIds): array {
            $row = [
                'opinion_date' => $entry->opinion_date->format('d/m/Y'),
                'project' => $entry->proyecto->codigo.' — '.($entry->proyecto->nombre_alias ?: $entry->proyecto->descripcion),
                'place_name' => $entry->place_name,
                'opinion' => BuzonEntry::OPINIONS[$entry->opinion] ?? $entry->opinion,
                'feedback_type' => BuzonEntry::FEEDBACK_TYPES[$entry->feedback_type] ?? $entry->feedback_type,
                'full_name' => $entry->full_name ?: 'Anónimo / Sin nombre',
                'user_name' => $entry->submitted_via_public_link
                    ? 'Externo (sin usuario) · Responsable del enlace: '.($entry->publicRecipient?->name ?: 'Usuario no disponible')
                    : ($entry->creator?->name ?: 'Usuario no disponible'),
                'origin' => $entry->submitted_via_public_link ? 'Enlace externo' : 'Interno',
                'age_group' => BuzonEntry::AGE_GROUPS[$entry->age_group] ?? 'No indicado',
            ];
            if (! $export) $row['actions'] = view('buzon.partials.entry-actions', compact('entry', 'manageableIds', 'visibleListIds'))->render();
            return $row;
        });

        return response()->json(['draw' => (int) $input['draw'], 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    private function search(Builder $query, string $search): void
    {
        $normalized = mb_strtolower(Str::ascii($search));
        $date = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search) ? implode('-', array_reverse(explode('/', $search))) : $search;
        $query->where(function (Builder $match) use ($search, $normalized, $date): void {
            $match->where('full_name', 'like', '%'.$search.'%')->orWhere('place_name', 'like', '%'.$search.'%')
                ->orWhere('opinion_date', 'like', '%'.$date.'%')
                ->orWhereHas('proyecto', fn (Builder $project) => $project->where('codigo', 'like', '%'.$search.'%')
                    ->orWhere('nombre_alias', 'like', '%'.$search.'%')->orWhere('descripcion', 'like', '%'.$search.'%'))
                ->orWhereHas('creator', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('publicRecipient', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%'));
            foreach (['opinion' => BuzonEntry::OPINIONS, 'feedback_type' => BuzonEntry::FEEDBACK_TYPES, 'age_group' => BuzonEntry::AGE_GROUPS] as $field => $labels) {
                foreach ($labels as $value => $label) {
                    if (str_contains(mb_strtolower(Str::ascii($label)), $normalized)) $match->orWhere($field, $value);
                }
            }
            if (str_contains('anonimo / sin nombre', $normalized)) $match->orWhereNull('full_name')->orWhere('full_name', '');
            if (str_contains('no indicado', $normalized)) $match->orWhereNull('age_group');
            foreach ([0 => 'interno', 1 => 'enlace externo (sin usuario)'] as $value => $label) {
                if (str_contains($label, $normalized)) $match->orWhere('submitted_via_public_link', $value);
            }
        });
    }
}
