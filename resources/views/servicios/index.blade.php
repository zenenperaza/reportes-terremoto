@extends('layouts.app')
@section('title','Servicios | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Configuración</p><h1>Servicios</h1><p class="muted">Administre el catálogo maestro de servicios.</p></div><a class="button button-primary" href="{{ route('servicios.create') }}">+ Nuevo servicio</a></section>
<section class="content-card">@if($servicios->isEmpty())<div class="empty-state"><p>No hay servicios registrados.</p></div>@else
<div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Descripción</th><th>Actividades asignadas</th><th></th></tr></thead><tbody>
@foreach($servicios as $servicio)<tr><td><strong>{{ $servicio->nombre }}</strong></td><td class="catalog-description">{{ $servicio->descripcion ?: 'Sin descripción' }}</td><td>{{ $servicio->asignaciones_actividades_count }}</td><td class="row-actions"><a href="{{ route('servicios.edit',$servicio) }}">Editar</a>@if($servicio->asignaciones_actividades_count===0)<form action="{{ route('servicios.destroy',$servicio) }}" method="post" onsubmit="return confirm('¿Eliminar este servicio?');">@csrf @method('DELETE')<button class="danger-link" type="submit">Eliminar</button></form>@endif</td></tr>@endforeach
</tbody></table></div><div class="pagination">{{ $servicios->links() }}</div>@endif</section>
@endsection
