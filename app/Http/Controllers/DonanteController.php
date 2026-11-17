<?php

namespace App\Http\Controllers;

use App\Models\Donante;
use App\Support\DonorLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DonanteController extends Controller
{
    public function index(): View
    {
        return view('donantes.index', ['donantes' => Donante::withCount('proyectos')->orderBy('nombre')->paginate(20)]);
    }

    public function create(): View
    {
        return view('donantes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $logo = $request->hasFile('logo') ? DonorLogo::store($request->file('logo')) : null;
        try {
            DB::transaction(function () use ($data, $logo): void {
                $donante = new Donante(collect($data)->except(['logo', 'remove_logo'])->all());
                $donante->logo_path = $logo;
                $donante->save();
            });
        } catch (\Throwable $exception) {
            DonorLogo::delete($logo);
            throw $exception;
        }
        return redirect()->route('donantes.index')->with('success', 'Donante creado correctamente.');
    }

    public function edit(Donante $donante): View
    {
        return view('donantes.edit', compact('donante'));
    }

    public function update(Request $request, Donante $donante): RedirectResponse
    {
        $data = $this->validated($request, $donante);
        $logo = $request->hasFile('logo') ? DonorLogo::store($request->file('logo')) : null;
        $oldLogo = null;
        try {
            DB::transaction(function () use ($data, $donante, $logo, &$oldLogo): void {
                $current = Donante::whereKey($donante->id)->lockForUpdate()->firstOrFail();
                $current->fill(collect($data)->except(['logo', 'remove_logo'])->all());
                if ($logo || ($data['remove_logo'] ?? false)) {
                    $oldLogo = $current->logo_path;
                    $current->logo_path = $logo;
                }
                $current->save();
            });
        } catch (\Throwable $exception) {
            DonorLogo::delete($logo);
            throw $exception;
        }
        if ($oldLogo !== $logo) DonorLogo::delete($oldLogo);
        return redirect()->route('donantes.index')->with('success', 'Donante actualizado correctamente.');
    }

    public function destroy(Donante $donante): RedirectResponse
    {
        if ($donante->proyectos()->exists()) {
            return back()->with('error', 'No puede eliminar el donante porque tiene proyectos asociados. Puede marcarlo como inactivo.');
        }
        $logo = $donante->logo_path;
        $donante->delete();
        DonorLogo::delete($logo);
        return redirect()->route('donantes.index')->with('success', 'Donante eliminado correctamente.');
    }

    private function validated(Request $request, ?Donante $donante = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('donantes')->ignore($donante)],
            'estatus' => ['required', 'boolean'],
            'enlaces' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:2048',
                'dimensions:min_width=32,min_height=32,max_width=2000,max_height=2000'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], [
            'logo.image' => 'El logo debe ser una imagen válida.',
            'logo.mimes' => 'El logo debe ser PNG, JPG o WebP. No se permite SVG.',
            'logo.extensions' => 'El archivo del logo debe tener extensión PNG, JPG, JPEG o WebP.',
            'logo.max' => 'El logo no puede superar 2 MB.',
            'logo.dimensions' => 'El logo debe medir entre 32 y 2000 píxeles de ancho y alto.',
        ]);
        if ($request->hasFile('logo') && $request->boolean('remove_logo')) {
            throw ValidationException::withMessages(['logo' => 'Seleccione un logo nuevo o marque Quitar logo, no ambas opciones.']);
        }
        return $data;
    }
}
