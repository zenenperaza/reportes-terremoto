<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\SummaryIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SummaryIndicatorController extends Controller
{
    public function index()
    {
        return view('summary-indicators.index', ['summaries' => SummaryIndicator::with('proyecto')->withCount('assignments')->orderBy('code')->paginate(20)]);
    }

    public function create()
    {
        return view('summary-indicators.form', ['summaryIndicator' => new SummaryIndicator(['is_active' => true]), 'projects' => Proyecto::orderBy('codigo')->get()]);
    }

    public function store(Request $request)
    {
        SummaryIndicator::create($this->validated($request));
        return redirect()->route('summary-indicators.index')->with('success', 'Indicador Resumen creado. Ahora puede asignarle indicadores de su proyecto.');
    }

    public function edit(SummaryIndicator $summaryIndicator)
    {
        return view('summary-indicators.form', compact('summaryIndicator') + ['projects' => Proyecto::orderBy('codigo')->get()]);
    }

    public function update(Request $request, SummaryIndicator $summaryIndicator)
    {
        $data = $this->validated($request, $summaryIndicator);
        DB::transaction(function () use ($data, $summaryIndicator): void {
            $current = SummaryIndicator::lockForUpdate()->findOrFail($summaryIndicator->id);
            if ((int) $current->proyecto_id !== (int) $data['proyecto_id'] && $current->assignments()->exists()) {
                if (! empty($data['reset_assignments_on_project_change'])) {
                    $current->assignments()->detach();
                } elseif ($current->proyecto_id !== null || $current->assignments()->where('indicador_proyecto.proyecto_id', '!=', $data['proyecto_id'])->exists()) {
                    throw ValidationException::withMessages(['proyecto_id' => 'Existen asignaciones anteriores. Para cambiar de proyecto confirme que desea reiniciarlas; no se modificarán registros ni beneficiarios.']);
                }
            }
            unset($data['reset_assignments_on_project_change']);
            $current->update($data);
        });
        return redirect()->route('summary-indicators.index')->with('success', 'Indicador Resumen actualizado.');
    }

    public function destroy(SummaryIndicator $summaryIndicator)
    {
        if ($summaryIndicator->assignments()->exists()) {
            return back()->with('error', 'Quite primero las asignaciones o desactive el Indicador Resumen para conservar su configuración.');
        }
        $summaryIndicator->delete();
        return redirect()->route('summary-indicators.index')->with('success', 'Indicador Resumen eliminado. No se modificaron registros.');
    }

    private function validated(Request $request, ?SummaryIndicator $summaryIndicator = null): array
    {
        $request->merge(collect($request->only(['code', 'name', 'description']))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
        return $request->validate([
            'proyecto_id' => ['required', 'integer', 'exists:proyectos,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('summary_indicators')->ignore($summaryIndicator)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'reset_assignments_on_project_change' => [$summaryIndicator ? 'nullable' : 'prohibited', 'boolean'],
        ]);
    }

    public function assignments(Request $request)
    {
        $filters = $request->validate(['summary_indicator_id' => ['nullable', 'integer', 'exists:summary_indicators,id'],
            'proyecto_id' => ['nullable', 'integer', 'exists:proyectos,id']]);
        $summary = filled($filters['summary_indicator_id'] ?? null) ? SummaryIndicator::with('proyecto')->findOrFail($filters['summary_indicator_id']) : null;
        $project = $summary?->proyecto;
        if (filled($filters['proyecto_id'] ?? null) && (int) $filters['proyecto_id'] !== (int) $summary?->proyecto_id) {
            throw ValidationException::withMessages(['proyecto_id' => 'El proyecto no corresponde al Indicador Resumen seleccionado.']);
        }
        return view('summary-indicators.assignments', [
            'summaries' => SummaryIndicator::with('proyecto')->orderBy('code')->get(),
            'summary' => $summary, 'project' => $project,
            'assignments' => $project ? $project->asignacionesIndicadores()->whereNotNull('sector_proyecto_id')
                ->with(['indicador', 'asignacionSector.sector'])->orderBy('id')->get() : collect(),
            'selected' => $summary && $project ? $summary->assignments()->where('indicador_proyecto.proyecto_id', $project->id)->pluck('indicador_proyecto.id')->all() : [],
        ]);
    }

    public function saveAssignments(Request $request)
    {
        $data = $request->validate([
            'summary_indicator_id' => ['required', 'integer', 'exists:summary_indicators,id'],
            'proyecto_id' => ['nullable', 'integer', 'exists:proyectos,id'],
            'assignment_ids' => ['nullable', 'array', 'max:1000'],
            'assignment_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        DB::transaction(function () use ($data): void {
            $summary = SummaryIndicator::lockForUpdate()->findOrFail($data['summary_indicator_id']);
            if (! $summary->proyecto_id || (filled($data['proyecto_id'] ?? null) && (int) $data['proyecto_id'] !== $summary->proyecto_id)) {
                throw ValidationException::withMessages(['proyecto_id' => 'El resumen debe tener un proyecto asignado y solo admite indicadores de ese proyecto.']);
            }
            validator($data, ['assignment_ids.*' => [Rule::exists('indicador_proyecto', 'id')
                ->where('proyecto_id', $summary->proyecto_id)->whereNotNull('sector_proyecto_id')]])->validate();
            $selected = collect($data['assignment_ids'] ?? [])->map(fn ($id) => (int) $id);
            $summary->assignments()->sync($selected->all());
        });
        return redirect()->route('summary-indicators.assignments', ['summary_indicator_id' => $data['summary_indicator_id']])
            ->with('success', 'Indicadores asignados al resumen para este proyecto. No se modificaron registros ni beneficiarios.');
    }
}
