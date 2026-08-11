@extends('layouts.app')
@section('title','Actividades | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Configuración</p><h1>Actividades</h1><p class="muted">Administre el catálogo maestro de actividades.</p></div><a class="button button-primary" href="{{ route('actividades.create') }}">+ Nueva actividad</a></section>
<section class="content-card">@if($actividades->isEmpty())<div class="empty-state"><p>No hay actividades registradas.</p></div>@else
<div class="table-wrap"><table><thead><tr><th>Código</th><th>Descripción</th><th>Indicadores asignados</th><th></th></tr></thead><tbody>
@foreach($actividades as $actividad)<tr><td><strong>{{ $actividad->codigo }}</strong></td><td class="catalog-description">{{ $actividad->descripcion }}</td><td>{{ $actividad->asignaciones_indicadores_count }}</td><td class="row-actions"><a href="{{ route('actividades.edit',$actividad) }}">Editar</a>@if($actividad->asignaciones_indicadores_count===0)<form action="{{ route('actividades.destroy',$actividad) }}" method="post" onsubmit="return confirm('¿Eliminar esta actividad?');">@csrf @method('DELETE')<button class="danger-link" type="submit">Eliminar</button></form>@endif</td></tr>@endforeach
</tbody></table></div><div class="pagination">{{ $actividades->links() }}</div>@endif</section>
@endsection
