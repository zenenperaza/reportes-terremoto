@extends('layouts.app')
@section('title', 'Donantes | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Configuraci&oacute;n</p><h1>Donantes</h1><p class="muted">Administre las organizaciones que financian los proyectos.</p></div><a class="button button-primary" href="{{ route('donantes.create') }}">+ Nuevo donante</a></section>
<section class="content-card">
@if($donantes->isEmpty())<div class="empty-state"><p>No hay donantes registrados.</p></div>@else
<div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Enlaces</th><th>Estado</th><th>Proyectos</th><th></th></tr></thead><tbody>
@foreach($donantes as $donante)<tr><td><strong>{{ $donante->nombre }}</strong></td><td>@if($donante->enlaces)<a href="{{ $donante->enlaces }}" target="_blank" rel="noopener noreferrer">Abrir enlace</a>@else<span class="muted">Sin enlace</span>@endif</td><td><span class="status {{ $donante->estatus ? 'status-active' : 'status-inactive' }}">{{ $donante->estatus ? 'Activo' : 'Inactivo' }}</span></td><td>{{ $donante->proyectos_count }}</td><td class="row-actions"><a href="{{ route('donantes.edit',$donante) }}">Editar</a>@if($donante->proyectos_count===0)<form action="{{ route('donantes.destroy',$donante) }}" method="post" onsubmit="return confirm('&iquest;Eliminar este donante?');">@csrf @method('DELETE')<button class="danger-link" type="submit">Eliminar</button></form>@endif</td></tr>@endforeach
</tbody></table></div><div class="pagination">{{ $donantes->links() }}</div>@endif
</section>
@endsection
