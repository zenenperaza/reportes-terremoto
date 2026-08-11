@extends('layouts.app')
@section('title','Indicadores | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Configuraci&oacute;n</p><h1>Indicadores</h1><p class="muted">Administre el cat&aacute;logo de indicadores de seguimiento.</p></div><a class="button button-primary" href="{{ route('indicadores.create') }}">+ Nuevo indicador</a></section>
<section class="content-card">@if($indicadores->isEmpty())<div class="empty-state"><p>No hay indicadores registrados.</p></div>@else
<div class="table-wrap"><table><thead><tr><th>C&oacute;digo</th><th>Indicador</th><th>Unidad</th><th>Coordinaci&oacute;n</th><th>Rango de edad</th><th>Proyectos</th><th></th></tr></thead><tbody>
@foreach($indicadores as $indicador)<tr><td><strong>{{ $indicador->codigo }}</strong></td><td class="catalog-description">{{ $indicador->descripcion }}</td><td>{{ $indicador->unidad_conteo }}</td><td><span class="catalog-tag">{{ $indicador->espacio_coordinacion }}</span></td><td><span class="catalog-tag">{{ $indicador->edad_desde }} a {{ $indicador->edad_hasta }} a&ntilde;os</span></td><td>{{ $indicador->proyectos_count }}</td><td class="row-actions"><a href="{{ route('indicadores.edit',$indicador) }}">Editar</a>@if($indicador->proyectos_count===0)<form action="{{ route('indicadores.destroy',$indicador) }}" method="post" onsubmit="return confirm('&iquest;Eliminar este indicador?');">@csrf @method('DELETE')<button class="danger-link" type="submit">Eliminar</button></form>@endif</td></tr>@endforeach
</tbody></table></div><div class="pagination">{{ $indicadores->links() }}</div>@endif</section>
@endsection
