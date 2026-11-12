<section class="content-card" id="buzon-list-entries">
    <h2>Opiniones guardadas en este listado</h2>
    <p class="muted">Listado #{{ $listing->id }} · {{ number_format($listEntries->total()) }} opinión(es) disponibles para su usuario. Cada fila es una opinión, no un beneficiario.</p>
    <div class="table-wrap"><table class="buzon-table"><thead><tr>
        <th>N.º</th><th>Fecha de opinión</th><th>Lugar</th><th>Nombre</th><th>Opinión</th><th>Retroalimentación</th><th>Registrado por</th><th>Acciones</th>
    </tr></thead><tbody>
        @forelse($listEntries as $savedEntry)
        <tr>
            <td>{{ $listEntries->firstItem() + $loop->index }}</td>
            <td>{{ $savedEntry->opinion_date->format('d/m/Y') }}</td><td>{{ $savedEntry->place_name }}</td>
            <td>{{ $savedEntry->full_name ?: 'Anónimo / Sin nombre' }}</td>
            <td>{{ \App\Models\BuzonEntry::OPINIONS[$savedEntry->opinion] }}</td>
            <td>{{ \App\Models\BuzonEntry::FEEDBACK_TYPES[$savedEntry->feedback_type] }}</td>
            <td>{{ $savedEntry->creator?->name ?: 'Usuario no disponible' }}</td>
            <td><div class="buzon-entry-actions">
                <a href="{{ route('buzon.show', $savedEntry) }}">Ver opinión</a>
                @if(in_array($savedEntry->id, $listManageableEntryIds, true))
                    @can('editar buzon')<a href="{{ route('buzon.edit', $savedEntry) }}">Editar</a>@endcan
                @endif
                @include('buzon.partials.delete-button', ['entry' => $savedEntry, 'canManageEntry' => in_array($savedEntry->id, $listManageableEntryIds, true)])
            </div></td>
        </tr>
        @empty<tr><td colspan="8">No hay opiniones disponibles en este listado. Puede agregar nuevas opiniones si tiene permiso.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $listEntries->links() }}</div>
</section>
