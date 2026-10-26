<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBuzonEntryRequest;
use App\Models\BuzonEntry;
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
        return view('buzon.index', ['projects' => $projects, 'filters' => $filters, 'counts' => $counts,
            'entries' => $query->with('proyecto')->latest('opinion_date')->latest('id')->paginate(20)->withQueryString()]);
    }

    private function formData(Request $request, BuzonEntry $entry, bool $readOnly = false): array
    {
        $projects = $this->projects($request)->where(fn ($query) => $query->where('estatus', true)
            ->when($entry->exists, fn ($query) => $query->orWhere('proyectos.id', $entry->proyecto_id)))->get();
        $selectedProject = $readOnly ? $entry->proyecto_id : old('proyecto_id', $entry->proyecto_id ?? $request->query('proyecto_id')
            ?? ($projects->count() === 1 ? $projects->first()->id : ''));
        if (! is_scalar($selectedProject) && $selectedProject !== null) $selectedProject = '';
        if (! $entry->exists && filled($selectedProject)) {
            $allowed = $projects->contains(fn ($project) => (string) $project->id === (string) $selectedProject);
            abort_if(! $allowed && ! session()->hasOldInput(), 403);
            if (! $allowed) $selectedProject = '';
        }
        return compact('entry', 'projects', 'readOnly', 'selectedProject');
    }

    public function create(Request $request)
    {
        return view('buzon.form', $this->formData($request, new BuzonEntry(['opinion_date' => today(), 'disabilities' => []])));
    }

    public function store(SaveBuzonEntryRequest $request)
    {
        $entry = BuzonEntry::create($request->validated() + ['created_by' => $request->user()->id]);
        return redirect()->route('buzon.show', $entry)->with('success', 'Formulario del Buzón registrado. No se agregó a beneficiarios.');
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
        return view('buzon.form', $this->formData($request, $entry));
    }

    public function update(SaveBuzonEntryRequest $request, BuzonEntry $entry)
    {
        DB::transaction(function () use ($request, $entry): void {
            $current = BuzonEntry::visibleTo($request->user())->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($current->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Otra persona modificó esta entrada. Recargue el formulario antes de guardar.']);
            }
            $data = Arr::except($request->validated(), ['version']);
            $current->update($data + ['disabilities' => [], 'version' => $current->version + 1]);
        });
        return redirect()->route('buzon.show', $entry)->with('success', 'Formulario del Buzón actualizado.');
    }
}
