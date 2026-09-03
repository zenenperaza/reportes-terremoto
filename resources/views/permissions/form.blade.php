<div class="form-grid">
    <label>Nombre del permiso *
        <input type="text" name="name" value="{{ old('name', $permission?->name) }}" maxlength="125" required autofocus
               placeholder="Ej. exportar informes" @readonly($protectedPermission ?? false)>
        <small>Use un nombre breve que describa una acción.</small>
    </label>
</div>
@if($protectedPermission ?? false)
    <p class="form-note"><i class="ri-lock-line"></i> Este permiso es necesario para el funcionamiento del sistema y no puede renombrarse ni eliminarse.</p>
@endif
<div class="form-actions">
    <a class="button button-secondary" href="{{ route('permissions.index') }}">Cancelar</a>
    @unless($protectedPermission ?? false)<button class="button button-primary" type="submit">{{ $submitLabel }}</button>@endunless
</div>
