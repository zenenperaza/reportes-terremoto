<div class="buzon-entry-actions"><a href="{{ route('buzon.show', $entry) }}">Ver formulario</a>
    @if(in_array($entry->buzon_list_id, $visibleListIds, true))<a href="{{ route('buzon.lists.show', $entry->buzon_list_id) }}">Listado #{{ $entry->buzon_list_id }}</a>@endif
    @if(in_array($entry->id, $manageableIds, true))
        @can('editar buzon')<a href="{{ route('buzon.edit', $entry) }}">Editar</a>@endcan
    @endif
    @include('buzon.partials.delete-button', ['canManageEntry' => in_array($entry->id, $manageableIds, true)])
</div>
