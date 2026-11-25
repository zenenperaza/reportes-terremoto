<?php

namespace App\Services;

use App\Models\{BuzonList, Proyecto, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuzonListsDataTable
{
    /** The supplied query already enforces author/group visibility and project filters. */
    public function response(Request $request, Builder $query): JsonResponse
    {
        $input = $request->validate([
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:200'],
            'order' => ['nullable', 'array', 'max:1'],
            'order.*.column' => ['required', 'integer', 'between:0,4'],
            'order.*.dir' => ['required', Rule::in(['asc', 'desc'])],
        ]);
        $total = (clone $query)->count();
        $search = trim($input['search']['value'] ?? '');
        if ($search !== '') {
            $date = preg_replace_callback('/^(\d{2})\/(\d{2})\/(\d{4})/',
                fn ($match) => $match[3].'-'.$match[2].'-'.$match[1], $search);
            $query->where(function (Builder $match) use ($search, $date): void {
                $match->where('buzon_lists.created_at', 'like', '%'.$date.'%')
                    ->orWhereHas('proyecto', fn (Builder $project) => $project->where('codigo', 'like', '%'.$search.'%')
                        ->orWhere('nombre_alias', 'like', '%'.$search.'%')->orWhere('descripcion', 'like', '%'.$search.'%'))
                    ->orWhereHas('creator', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%'));
                if (preg_match('/^#?\d+$/', $search)) $match->orWhere('buzon_lists.id', ltrim($search, '#'));
            });
        }
        $filtered = (clone $query)->count();
        $query->with(['proyecto', 'creator'])
            ->withCount(['entries' => fn (Builder $entries) => $entries->visibleTo($request->user())]);
        $direction = $input['order'][0]['dir'] ?? 'desc';
        $column = (int) ($input['order'][0]['column'] ?? 0);
        if ($column === 2) {
            $query->orderBy(Proyecto::select('codigo')->whereColumn('proyectos.id', 'buzon_lists.proyecto_id'), $direction);
        } elseif ($column === 3) {
            $query->orderBy(User::withTrashed()->select('name')->whereColumn('users.id', 'buzon_lists.created_by'), $direction);
        } else {
            $query->orderBy([0 => 'buzon_lists.id', 1 => 'buzon_lists.created_at', 4 => 'entries_count'][$column], $direction);
        }
        $lists = $query->orderByDesc('buzon_lists.id')->skip($input['start'] ?? 0)->take($input['length'] ?? 20)->get();
        $appendableListIds = $request->user()->can('crear buzon')
            ? BuzonList::appendableBy($request->user())->whereKey($lists->modelKeys())->pluck('id')->all() : [];
        $data = $lists->map(fn (BuzonList $listing) => [
            'id' => $listing->id,
            'created_at' => $listing->created_at->format('d/m/Y H:i'),
            'project' => $listing->proyecto->codigo.' — '.($listing->proyecto->nombre_alias ?: $listing->proyecto->descripcion)
                .($listing->proyecto->estatus ? '' : ' (Inactivo)'),
            'creator' => $listing->creator?->name ?: 'Usuario no disponible',
            'entries_count' => $listing->entries_count,
            'actions' => view('buzon.lists.partials.actions', ['listing' => $listing, 'appendableListIds' => $appendableListIds])->render(),
        ]);

        return response()->json(['draw' => (int) $input['draw'], 'recordsTotal' => $total,
            'recordsFiltered' => $filtered, 'data' => $data]);
    }
}
