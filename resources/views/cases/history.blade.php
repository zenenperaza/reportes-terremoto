@extends('layouts.app')
@section('title', 'Historial del expediente | SIA')
@include('cases._assets')
@section('content')
<div class="case-module">
    <header class="case-heading"><div><span class="case-eyebrow">{{ $caseRecord->reference }}</span><h1>Historial y accesos</h1><p>{{ $caseRecord->full_name }}</p></div><a class="btn btn-outline-primary" href="{{ route('cases.show', $caseRecord) }}">Volver al expediente</a></header>
    <section class="case-surface">
        <div class="case-surface-heading"><h2>Actividad del expediente</h2><span class="text-muted">{{ $events->total() }} eventos</span></div>
        <div class="table-responsive"><table class="table case-table"><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Campos modificados</th></tr></thead><tbody>
        @foreach($events as $event)
            <tr><td>{{ $event->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $event->user_name }}</td><td>{{ ['created' => 'Creó el expediente', 'updated' => 'Actualizó el expediente', 'assigned' => 'Cambió el responsable', 'viewed' => 'Consultó el expediente', 'opened_edit' => 'Abrió la edición', 'viewed_history' => 'Consultó el historial', 'uploaded' => 'Adjuntó un archivo', 'downloaded' => 'Descargó un archivo'][$event->action] ?? $event->action }}</td><td>{{ collect($event->changed_fields)->map(fn($field) => config('case-management.fields.'.$field, $field))->implode(', ') ?: '—' }}</td></tr>
        @endforeach
        </tbody></table></div><div class="p-3">{{ $events->links() }}</div>
    </section>
</div>
@endsection
