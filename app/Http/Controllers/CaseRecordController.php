<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCaseRecordRequest;
use App\Models\CaseRecord;
use App\Models\FamilyRecord;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\State;
use App\Models\User;
use App\Support\CaseFormSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseRecordController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CaseRecord::class);
        $filters = $this->filters($request);
        $query = CaseRecord::visibleTo($request->user());
        $counts = (clone $query)->selectRaw('risk_level, COUNT(*) as total')->groupBy('risk_level')->pluck('total', 'risk_level');
        $this->applyFilters($query, $filters);

        return view('cases.index', [
            'cases' => $query->with(['assignee', 'state'])->latest('registered_on')->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters, 'counts' => $counts,
            'states' => State::orderBy('name')->get(['id', 'name']),
            'assignees' => User::withTrashed()->whereIn('id', CaseRecord::visibleTo($request->user())->select('assigned_to'))->orderBy('name')->get(['id', 'name']),
            'types' => $this->types($request),
        ]);
    }

    public function start(Request $request)
    {
        Gate::authorize('create', CaseRecord::class);
        $filters = $this->filters($request);
        $matches = null;
        if (filled($filters['q'] ?? null)) {
            $query = CaseRecord::visibleTo($request->user());
            $this->applyFilters($query, Arr::only($filters, ['q', 'search_by']));
            $matches = $query->with('assignee')->orderBy('full_name')->paginate(10)->withQueryString();
        }

        return view('cases.start', compact('filters', 'matches'));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', CaseRecord::class);
        $caseRecord = new CaseRecord([
            'registered_on' => today(), 'case_type' => 'general', 'risk_level' => 'pending', 'status' => 'open',
            'sex' => 'not_specified', 'consent_status' => 'pending', 'assigned_to' => $request->user()->id,
            'share_services' => false, 'share_reports' => false,
        ]);

        return view('cases.form', $this->formData($request, $caseRecord, false));
    }

    public function store(SaveCaseRecordRequest $request)
    {
        $caseRecord = DB::transaction(function () use ($request): CaseRecord {
            $data = Arr::except($request->validated(), ['version']);
            $data['form_data'] = CaseFormSchema::merge([], $data['form_data'] ?? [], $request->user()->can('supervisar casos'));
            $data['assigned_to'] = $data['assigned_to'] ?? $request->user()->id;
            if (! empty($data['birth_date'])) {
                $data['age_at_registration'] = null;
            }
            $case = CaseRecord::create($data + ['reference' => 'CS-'.Str::ulid(), 'created_by' => $request->user()->id]);
            $this->event($case, $request, 'created');

            return $case;
        });

        return $this->savedResponse($request, $caseRecord, 'Expediente creado correctamente.');
    }

    public function show(Request $request, CaseRecord $caseRecord)
    {
        Gate::authorize('view', $caseRecord);
        $this->event($caseRecord, $request, 'viewed');

        return view('cases.form', $this->formData($request, $caseRecord, true));
    }

    public function edit(Request $request, CaseRecord $caseRecord)
    {
        Gate::authorize('update', $caseRecord);
        $this->event($caseRecord, $request, 'opened_edit');

        return view('cases.form', $this->formData($request, $caseRecord, false));
    }

    public function update(SaveCaseRecordRequest $request, CaseRecord $caseRecord)
    {
        $caseRecord = DB::transaction(function () use ($request, $caseRecord): CaseRecord {
            $current = CaseRecord::whereKey($caseRecord->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $current);
            if ($current->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Otra persona modificó este expediente. Abra nuevamente el caso para revisar la versión actual antes de guardar.']);
            }
            $data = Arr::except($request->validated(), ['version']);
            if (array_key_exists('form_data', $data)) {
                $data['form_data'] = CaseFormSchema::merge($current->form_data ?? [], $data['form_data'] ?? [], $request->user()->can('supervisar casos'));
            }
            $data['assigned_to'] = $data['assigned_to'] ?? $current->assigned_to;
            if (! empty($data['birth_date'])) {
                $data['age_at_registration'] = null;
            }
            $current->fill($data);
            $fields = array_keys($current->getDirty());
            if ($fields !== []) {
                $current->version++;
                $current->save();
                $this->event($current, $request, in_array('assigned_to', $fields, true) ? 'assigned' : 'updated', $fields);
            }

            return $current;
        });

        return $this->savedResponse($request, $caseRecord, 'Expediente actualizado correctamente.');
    }

    public function history(Request $request, CaseRecord $caseRecord)
    {
        Gate::authorize('history', $caseRecord);
        $this->event($caseRecord, $request, 'viewed_history');

        return view('cases.history', [
            'caseRecord' => $caseRecord,
            'events' => $caseRecord->events()->latest('id')->paginate(25),
        ]);
    }

    private function event(CaseRecord $case, Request $request, string $action, array $fields = []): void
    {
        $case->events()->create(['user_id' => $request->user()->id, 'user_name' => $request->user()->name, 'action' => $action, 'changed_fields' => $fields]);
    }

    private function savedResponse(Request $request, CaseRecord $case, string $message)
    {
        return redirect()->route($request->user()->can('view', $case) ? 'cases.show' : 'cases.index', $request->user()->can('view', $case) ? $case : [])
            ->with('success', $message);
    }

    private function types(Request $request): array
    {
        return $request->user()->can('gestionar casos vbg') ? config('case-management.types') : Arr::except(config('case-management.types'), 'vbg');
    }

    private function formData(Request $request, CaseRecord $caseRecord, bool $readOnly): array
    {
        $caseRecord->load(['assignee', 'project', 'state', 'municipality', 'parish', 'attachments']);
        $projects = Proyecto::query()->where(function (Builder $query) use ($request, $caseRecord): void {
            $query->where(function (Builder $active) use ($request): void {
                $active->where('estatus', true);
                if (! $request->user()->can('supervisar casos')) {
                    $active->whereHas('users', fn (Builder $users) => $users->whereKey($request->user()->id));
                }
            });
            if ($caseRecord->proyecto_id) {
                $query->orWhere('id', $caseRecord->proyecto_id);
            }
        })->orderBy('codigo')->get(['id', 'codigo', 'descripcion']);
        $assignees = collect();
        if (! $readOnly && $request->user()->can('asignar casos')) {
            $assignees = User::where('is_active', true)->permission('ver casos')->with('roles.permissions', 'permissions')->orderBy('name')->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'vbg' => $user->can('gestionar casos vbg')]);
        }
        $stateId = old('state_id', $caseRecord->state_id);
        $municipalityId = old('municipality_id', $caseRecord->municipality_id);

        return [
            'caseRecord' => $caseRecord, 'readOnly' => $readOnly, 'projects' => $projects, 'assignees' => $assignees, 'types' => $this->types($request),
            'families' => FamilyRecord::visibleTo($request->user())->orderBy('name')->get(['id', 'reference', 'name']),
            'family' => $caseRecord->family_record_id ? FamilyRecord::visibleTo($request->user())->whereKey($caseRecord->family_record_id)->first() : null,
            'relatedCases' => $caseRecord->family_record_id ? CaseRecord::visibleTo($request->user())->where('family_record_id', $caseRecord->family_record_id)->where('id', '!=', $caseRecord->id)->orderBy('full_name')->get(['id', 'reference', 'full_name']) : collect(),
            'recentEvents' => $caseRecord->exists && $request->user()->can('history', $caseRecord) ? $caseRecord->events()->latest('id')->limit(50)->get() : collect(),
            'states' => State::orderBy('name')->get(['id', 'name']),
            'municipalities' => $stateId ? Municipality::where('state_id', $stateId)->orderBy('name')->get(['id', 'name']) : collect(),
            'parishes' => $municipalityId ? Parish::where('municipality_id', $municipalityId)->orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'search_by' => ['nullable', Rule::in(['name', 'id', 'phone'])],
            'case_type' => ['nullable', Rule::in(array_keys(config('case-management.types')))],
            'risk_level' => ['nullable', Rule::in(array_keys(config('case-management.risks')))],
            'sex' => ['nullable', Rule::in(array_keys(config('case-management.sexes')))],
            'assigned_to' => ['nullable', 'integer'], 'state_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(function (Builder $search) use ($filters, $term): void {
                match ($filters['search_by'] ?? 'name') {
                    'id' => $search->where('reference', $term)->orWhere('document_number', $term),
                    'phone' => $search->where('phone', $term),
                    default => $search->where('full_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%'),
                };
            });
        }
        foreach (['case_type', 'risk_level', 'sex', 'assigned_to', 'state_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['from'] ?? null) {
            $query->whereDate('registered_on', '>=', $filters['from']);
        }
        if ($filters['to'] ?? null) {
            $query->whereDate('registered_on', '<=', $filters['to']);
        }
    }
}
