@extends('layouts.app')
@section('title', 'Asignar Indicadores Resumen | SIA')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Indicadores Resumen</p><h1>Asignar indicadores del proyecto</h1><p class="muted">Seleccione el resumen. Solo se mostrarán indicadores del proyecto al que pertenece.</p></div><a class="button button-secondary" href="{{ route('summary-indicators.index') }}">Volver a Indicadores Resumen</a></section>
<section class="content-card"><form method="get" action="{{ route('summary-indicators.assignments') }}"><div class="form-grid two-cols">
<label>Indicador Resumen *<select name="summary_indicator_id" required><option value="">Seleccione un resumen</option>@foreach($summaries as $item)<option value="{{ $item->id }}" @selected($summary?->id === $item->id)>{{ $item->proyecto?->codigo ?? 'Proyecto pendiente' }} / {{ $item->code }} — {{ $item->name }}{{ $item->is_active ? '' : ' (Inactivo)' }}</option>@endforeach</select></label>
</div><div class="form-actions"><button class="button button-primary" type="submit">Mostrar indicadores</button></div></form></section>
@if($summary && !$project)<section class="content-card"><p>Este resumen debe tener un proyecto asignado antes de configurar indicadores o utilizarlo en los informes.</p><a class="button button-primary" href="{{ route('summary-indicators.edit', $summary) }}">Asignar proyecto en Editar</a></section>@endif
@if($summary && $project)
<section class="content-card"><h2>{{ $summary->code }} — {{ $summary->name }}</h2><p>{{ $project->codigo }} · {{ count($assignments) }} indicador(es) disponible(s).</p><form method="post" action="{{ route('summary-indicators.save-assignments') }}">@csrf @method('PUT')<input type="hidden" name="summary_indicator_id" value="{{ $summary->id }}"><input type="hidden" name="proyecto_id" value="{{ $project->id }}">
@php($chosen = array_map('strval', (array) old('assignment_ids', session()->hasOldInput() ? [] : $selected)))
<div class="table-wrap"><table><thead><tr><th>Seleccionar</th><th>Sector</th><th>Código</th><th>Indicador</th><th>Estado</th></tr></thead><tbody>
@forelse($assignments as $assignment)<tr><td><input type="checkbox" name="assignment_ids[]" value="{{ $assignment->id }}" aria-label="Incluir {{ $assignment->indicador->codigo }}" @checked(in_array((string) $assignment->id, $chosen, true))></td><td>{{ $assignment->asignacionSector?->sector?->name }}</td><td>{{ $assignment->indicador->codigo }}</td><td>{{ $assignment->indicador->descripcion }}</td><td>{{ $assignment->estatus ? 'Activo' : 'Inactivo (histórico)' }}</td></tr>@empty<tr><td colspan="5">El proyecto no tiene indicadores asignados a sectores.</td></tr>@endforelse
</tbody></table></div><p class="muted">Sin indicadores marcados, el resumen no incluirá registros. Solo se admiten indicadores de {{ $project->codigo }}; los otros resúmenes no se modifican.</p><div class="form-actions"><button class="button button-primary" type="submit">Guardar asignaciones</button></div></form></section>
@endif
@endsection
