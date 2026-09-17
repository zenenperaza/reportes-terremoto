@if($caseRecord->exists && auth()->user()->can('history', $caseRecord))
    @php($displayEvents = $recentEvents->filter(fn($event) => in_array($event->action, $actions)))
    <div class="table-responsive"><table class="table"><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th></tr></thead><tbody>
        @forelse($displayEvents as $event)<tr><td>{{ $event->created_at->format('d/m/Y H:i') }}</td><td>{{ $event->user_name }}</td><td>{{ ['created'=>'Creó el expediente','updated'=>'Actualizó el expediente','assigned'=>'Cambió el responsable','viewed'=>'Consultó el expediente','opened_edit'=>'Abrió la edición','viewed_history'=>'Consultó el historial','uploaded'=>'Adjuntó un archivo','downloaded'=>'Descargó un archivo'][$event->action] ?? $event->action }}</td></tr>@empty<tr><td colspan="3">No hay eventos recientes de este tipo.</td></tr>@endforelse
    </tbody></table></div>
    <a href="{{ route('cases.history', $caseRecord) }}">Ver historial completo</a><small class="d-block text-muted">Esta vista muestra los eventos correspondientes entre los últimos 50 del expediente.</small>
@else<p class="text-muted">El historial requiere un expediente guardado y permiso para consultar su actividad.</p>@endif
