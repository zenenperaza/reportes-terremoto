<div class="form-grid">
    <label>Nombre del rol *
        <input type="text" name="name" value="{{ old('name', $role?->name) }}" maxlength="125" required autofocus
               placeholder="Ej. Supervisor regional" @readonly($protectedRole ?? false)>
    </label>
</div>

<fieldset class="permission-selector">
    <legend>Permisos del rol</legend>
    <p class="muted">Seleccione las acciones que podrán realizar los usuarios que tengan este rol.</p>
    @php($selectedPermissions = collect(old('permissions', $role?->permissions?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))
    <div class="permission-grid">
        @forelse($permissions as $permission)
            @php($adminRequired = ($role?->name ?? null) === 'admin' && $permission->name === 'administrar sistema')
            <label class="permission-choice">
                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                       @checked($selectedPermissions->contains($permission->id) || $adminRequired) @disabled($adminRequired)>
                @if($adminRequired)<input type="hidden" name="permissions[]" value="{{ $permission->id }}">@endif
                <span><strong>{{ str($permission->name)->headline() }}</strong><small>Permiso: {{ $permission->name }}</small></span>
            </label>
        @empty
            <p>No existen permisos. <a href="{{ route('permissions.create') }}">Crear el primer permiso</a>.</p>
        @endforelse
    </div>
</fieldset>

@if($protectedRole ?? false)
    <p class="form-note"><i class="ri-information-line"></i> Este es un rol base. Puede modificar sus permisos, pero no cambiar su nombre ni eliminarlo.</p>
@endif

<div class="form-actions">
    <a class="button button-secondary" href="{{ route('roles.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
