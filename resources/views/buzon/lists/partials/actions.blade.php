<div class="buzon-entry-actions">
    <a href="{{ route('buzon.lists.show', $listing) }}">Ver listado</a>
    @if(in_array($listing->id, $appendableListIds, true))
        <a href="{{ route('buzon.create', ['list_id' => $listing->id]) }}">Agregar opiniones</a>
    @endif
</div>
