@can('eliminar buzon')
    @if($canManageEntry ?? false)
    <form method="post" action="{{ route('buzon.destroy', $entry) }}" class="buzon-delete-form" data-buzon-delete>
        @csrf
        @method('DELETE')
        <input type="hidden" name="version" value="{{ $entry->version }}">
        <button type="submit" class="button button-secondary buzon-delete-button">Eliminar</button>
    </form>
    @endif
@endcan
