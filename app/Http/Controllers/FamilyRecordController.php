<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFamilyRecordRequest;
use App\Models\CaseRecord;
use App\Models\FamilyRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyRecordController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', FamilyRecord::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = FamilyRecord::visibleTo($request->user());
        if (filled($filters['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('reference', $filters['q']));
        }

        return view('families.index', ['families' => $query->with('assignee')->latest('id')->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create()
    {
        Gate::authorize('create', FamilyRecord::class);

        return view('families.form', ['familyRecord' => new FamilyRecord(['registered_on' => today(), 'restricted' => false]), 'readOnly' => false, 'cases' => collect()]);
    }

    public function store(SaveFamilyRecordRequest $request)
    {
        $family = DB::transaction(function () use ($request) {
            $family = FamilyRecord::create(Arr::except($request->validated(), 'version') + ['reference' => 'FA-'.Str::ulid(), 'assigned_to' => $request->user()->id]);
            $this->event($family, $request, 'created');

            return $family;
        });

        return redirect()->route('families.show', $family)->with('success', 'Familia registrada correctamente.');
    }

    public function show(Request $request, FamilyRecord $familyRecord)
    {
        return $this->form($request, $familyRecord, true);
    }

    public function edit(Request $request, FamilyRecord $familyRecord)
    {
        return $this->form($request, $familyRecord, false);
    }

    private function form(Request $request, FamilyRecord $familyRecord, bool $readOnly)
    {
        Gate::authorize($readOnly ? 'view' : 'update', $familyRecord);
        $this->event($familyRecord, $request, $readOnly ? 'viewed' : 'opened_edit');

        // Nunca mostrar ni contar casos a los que no tiene acceso, aunque estén en esta familia.
        return view('families.form', ['familyRecord' => $familyRecord, 'readOnly' => $readOnly,
            'cases' => CaseRecord::visibleTo($request->user())->where('family_record_id', $familyRecord->id)->orderBy('full_name')->get(),
        ]);
    }

    public function update(SaveFamilyRecordRequest $request, FamilyRecord $familyRecord)
    {
        DB::transaction(function () use ($request, $familyRecord): void {
            $current = FamilyRecord::whereKey($familyRecord->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $current);
            if ($current->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'La familia fue modificada por otra persona. Recargue antes de guardar.']);
            }
            $current->fill(Arr::except($request->validated(), 'version'));
            $current->version++;
            $current->save();
            $this->event($current, $request, 'updated');
        });

        return redirect()->route('families.show', $familyRecord)->with('success', 'Familia actualizada correctamente.');
    }

    private function event(FamilyRecord $family, Request $request, string $action): void
    {
        DB::table('family_events')->insert(['family_record_id' => $family->id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name, 'action' => $action, 'created_at' => now()]);
    }
}
