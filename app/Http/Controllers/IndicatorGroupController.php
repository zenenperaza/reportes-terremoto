<?php

namespace App\Http\Controllers;

use App\Models\IndicatorGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndicatorGroupController extends Controller
{
    public function index(): View
    {
        return view('indicator-groups.index', [
            'groups' => IndicatorGroup::query()
                ->withCount('indicators')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('indicator-groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        IndicatorGroup::create($this->validated($request));

        return redirect()->route('indicator-groups.index')
            ->with('success', 'Grupo de indicadores creado correctamente.');
    }

    public function edit(IndicatorGroup $indicatorGroup): View
    {
        return view('indicator-groups.edit', compact('indicatorGroup'));
    }

    public function update(Request $request, IndicatorGroup $indicatorGroup): RedirectResponse
    {
        $indicatorGroup->update($this->validated($request, $indicatorGroup));

        return redirect()->route('indicator-groups.index')
            ->with('success', 'Grupo de indicadores actualizado correctamente.');
    }

    public function destroy(IndicatorGroup $indicatorGroup): RedirectResponse
    {
        if ($indicatorGroup->indicators()->exists()) {
            return back()->with('error', 'No puede eliminar el grupo mientras tenga indicadores asignados.');
        }

        $indicatorGroup->delete();

        return redirect()->route('indicator-groups.index')
            ->with('success', 'Grupo de indicadores eliminado correctamente.');
    }

    private function validated(Request $request, ?IndicatorGroup $indicatorGroup = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('indicator_groups', 'name')->ignore($indicatorGroup)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
