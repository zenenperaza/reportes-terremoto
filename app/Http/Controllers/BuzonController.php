<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBuzonEntryRequest;
use App\Models\BuzonEntry;
use App\Models\BuzonList;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuzonController extends Controller
{
    private function projects(Request $request)
    {
        return Proyecto::query()->when(! $request->user()->isAdministrator(), fn ($query) => $query
            ->whereHas('users', fn ($users) => $users->whereKey($request->user()->id)))->orderBy('codigo');
    }

    public function index(Request $request)
    {
        $projects = $this->projects($request)->get();
        $filters = $request->validate([
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['required', 'integer', 'distinct', Rule::in($projects->modelKeys())],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'opinion' => ['nullable', Rule::in(array_keys(BuzonEntry::OPINIONS))],
            'feedback_type' => ['nullable', Rule::in(array_keys(BuzonEntry::FEEDBACK_TYPES))],
        ], ['to.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.']);
        $filters += ['project_ids' => [], 'from' => '', 'to' => '', 'opinion' => '', 'feedback_type' => ''];
        $filters['project_ids'] = $filters['project_ids'] ?? [];
        $query = BuzonEntry::visibleTo($request->user())
            ->when(filled($filters['project_ids']), fn ($query) => $query->whereIn('proyecto_id', $filters['project_ids']))
            ->when(filled($filters['from']), fn ($query) => $query->whereDate('opinion_date', '>=', $filters['from']))
            ->when(filled($filters['to']), fn ($query) => $query->whereDate('opinion_date', '<=', $filters['to']))
            ->when(filled($filters['opinion']), fn ($query) => $query->where('opinion', $filters['opinion']))
            ->when(filled($filters['feedback_type']), fn ($query) => $query->where('feedback_type', $filters['feedback_type']));
        $counts = (clone $query)->selectRaw('opinion, COUNT(*) as total')->groupBy('opinion')->pluck('total', 'opinion');
        $entries = $query->with('proyecto')->latest('opinion_date')->latest('id')->paginate(20)->withQueryString();
        $visibleListIds = BuzonList::visibleTo($request->user())->whereIn('id', $entries->pluck('buzon_list_id')->filter())->pluck('id')->all();
        $manageableEntryIds = ($request->user()->can('editar buzon') || $request->user()->can('eliminar buzon'))
            ? BuzonEntry::manageableBy($request->user())->whereKey($entries->modelKeys())->pluck('id')->all() : [];
        return view('buzon.index', compact('projects', 'filters', 'counts', 'entries', 'manageableEntryIds', 'visibleListIds'));
    }

    public function lists(Request $request)
    {
        $projects = $this->projects($request)->get();
        $filters = $request->validate([
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['required', 'integer', 'distinct', Rule::in($projects->modelKeys())],
        ]) + ['project_ids' => []];
        $filters['project_ids'] ??= [];
        $lists = BuzonList::visibleTo($request->user())
            ->when($filters['project_ids'], fn ($query, $ids) => $query->whereIn('proyecto_id', $ids))
            ->with(['proyecto', 'creator'])
            ->withCount(['entries' => fn ($query) => $query->visibleTo($request->user())])
            ->latest('id')->paginate(20)->withQueryString();
        $appendableListIds = $request->user()->can('crear buzon')
            ? BuzonList::appendableBy($request->user())->whereKey($lists->modelKeys())->pluck('id')->all() : [];

        return view('buzon.lists.index', compact('projects', 'filters', 'lists', 'appendableListIds'));
    }

    public function showList(Request $request, BuzonList $listing)
    {
        abort_unless($this->canViewList($request, $listing), 404);

        return view('buzon.lists.show', $this->listData($request, $listing));
    }

    private function canViewList(Request $request, ?BuzonList $listing): bool
    {
        return $listing && $request->user()->can('ver buzon')
            && BuzonList::visibleTo($request->user())->whereKey($listing->id)->exists();
    }

    private function listData(Request $request, BuzonList $listing): array
    {
        $listing->loadMissing(['proyecto', 'creator']);
        $listEntries = $listing->entries()->visibleTo($request->user())->with('creator')->latest('id')
            ->paginate(20, ['*'], 'list_page')->withQueryString();
        $listManageableEntryIds = ($request->user()->can('editar buzon') || $request->user()->can('eliminar buzon'))
            ? BuzonEntry::manageableBy($request->user())->whereKey($listEntries->modelKeys())->pluck('id')->all() : [];
        $canAppendList = $request->user()->can('crear buzon')
            && BuzonList::appendableBy($request->user())->whereKey($listing->id)->exists();

        return compact('listing', 'listEntries', 'listManageableEntryIds', 'canAppendList');
    }

    private function formData(Request $request, BuzonEntry $entry, bool $readOnly = false, ?BuzonList $listing = null): array
    {
        $listing ??= $entry->exists ? $entry->listing : null;
        $projects = $this->projects($request)->where(fn ($query) => $query->where('estatus', true)
            ->when($entry->exists, fn ($query) => $query->orWhere('proyectos.id', $entry->proyecto_id)))->get();
        $selectedProject = $readOnly ? $entry->proyecto_id : old('proyecto_id', $entry->proyecto_id ?? $request->query('proyecto_id')
            ?? ($projects->count() === 1 ? $projects->first()->id : ''));
        if ($listing) $selectedProject = $listing->proyecto_id;
        if (! is_scalar($selectedProject) && $selectedProject !== null) $selectedProject = '';
        if (! $entry->exists && filled($selectedProject)) {
            $allowed = $projects->contains(fn ($project) => (string) $project->id === (string) $selectedProject);
            abort_if(! $allowed && ! session()->hasOldInput(), 403);
            if (! $allowed) $selectedProject = '';
        }
        $canManageEntry = $entry->exists && BuzonEntry::manageableBy($request->user())->whereKey($entry->id)->exists();
        $canViewList = $this->canViewList($request, $listing);
        $data = compact('entry', 'projects', 'readOnly', 'selectedProject', 'canManageEntry', 'listing', 'canViewList');
        if ($listing && ! $entry->exists && $canViewList) $data += $this->listData($request, $listing);

        return $data;
    }

    public function create(Request $request)
    {
        $input = validator(['list_id' => $request->query('list_id')], ['list_id' => ['nullable', 'integer', 'min:1']])->validate();
        $listing = empty($input['list_id']) ? null : BuzonList::appendableBy($request->user())->findOrFail($input['list_id']);

        return view('buzon.form', $this->formData($request, new BuzonEntry(['opinion_date' => today(), 'disabilities' => []]), false, $listing));
    }

    public function store(SaveBuzonEntryRequest $request)
    {
        $listing = DB::transaction(function () use ($request): BuzonList {
            $data = Arr::except($request->validated(), ['buzon_list_id']);
            $project = Proyecto::whereKey($data['proyecto_id'])->lockForUpdate()->firstOrFail();
            abort_unless($project->estatus && ($request->user()->isAdministrator()
                || $project->users()->whereKey($request->user()->id)->exists()), 403);
            if ($request->filled('buzon_list_id')) {
                $listing = BuzonList::appendableBy($request->user())->whereKey($request->integer('buzon_list_id'))->lockForUpdate()->firstOrFail();
                abort_unless((int) $listing->proyecto_id === (int) $project->id, 403);
            } else {
                $listing = BuzonList::create(['proyecto_id' => $project->id, 'created_by' => $request->user()->id]);
            }
            $listing->entries()->create($data + ['created_by' => $request->user()->id]);

            return $listing;
        });
        return redirect()->route('buzon.create', ['proyecto_id' => $listing->proyecto_id, 'list_id' => $listing->id])
            ->withInput([])
            ->with('success', 'Formulario del Buzón registrado. No se agregó a beneficiarios. Puede registrar la siguiente opinión.');
    }

    private function ensureVisible(Request $request, BuzonEntry $entry): void
    {
        abort_unless(BuzonEntry::visibleTo($request->user())->whereKey($entry->id)->exists(), 404);
    }

    public function show(Request $request, BuzonEntry $entry)
    {
        $this->ensureVisible($request, $entry);
        return view('buzon.form', $this->formData($request, $entry, true));
    }

    public function edit(Request $request, BuzonEntry $entry)
    {
        $this->ensureVisible($request, $entry);
        $this->ensureManageable($request, $entry);
        return view('buzon.form', $this->formData($request, $entry));
    }

    public function update(SaveBuzonEntryRequest $request, BuzonEntry $entry)
    {
        DB::transaction(function () use ($request, $entry): void {
            $current = BuzonEntry::visibleTo($request->user())->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->ensureManageable($request, $current);
            if ($current->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Otra persona modificó esta entrada. Recargue el formulario antes de guardar.']);
            }
            $data = Arr::except($request->validated(), ['version', 'buzon_list_id']);
            $current->update($data + ['disabilities' => [], 'version' => $current->version + 1]);
        });
        return ($this->canViewList($request, $entry->listing)
            ? redirect()->route('buzon.lists.show', $entry->listing)
            : redirect()->route('buzon.show', $entry))->with('success', 'Formulario del Buzón actualizado.');
    }

    public function destroy(Request $request, BuzonEntry $entry)
    {
        $this->ensureVisible($request, $entry);
        $this->ensureManageable($request, $entry);
        $request->validate(['version' => ['required', 'integer', 'min:1']], [
            'version.required' => 'Recargue el Buzón antes de eliminar el formulario.',
        ]);
        DB::transaction(function () use ($request, $entry): void {
            $current = BuzonEntry::visibleTo($request->user())->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->ensureManageable($request, $current);
            if ($current->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Otra persona modificó esta entrada. Revísela antes de eliminarla.']);
            }
            $current->delete();
        });

        return ($this->canViewList($request, $entry->listing)
            ? redirect()->route('buzon.lists.show', $entry->listing)
            : redirect()->route('buzon.index', ['project_ids' => [$entry->proyecto_id]]))
            ->with('success', 'Formulario del Buzón eliminado correctamente.');
    }

    private function ensureManageable(Request $request, BuzonEntry $entry): void
    {
        abort_unless(BuzonEntry::manageableBy($request->user())->whereKey($entry->id)->exists(), 403,
            'Solo puede modificar sus propios formularios o los de compañeros con quienes comparte un grupo activo con edición cruzada habilitada.');
    }
}
