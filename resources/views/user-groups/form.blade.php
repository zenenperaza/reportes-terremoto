<div class="form-grid two-cols">
    <label>Nombre del grupo *
        <input type="text" name="name" value="{{ old('name', $userGroup?->name) }}" maxlength="120" required autofocus placeholder="Ej. Equipo Zulia">
    </label>
    <label>Estado *
        <select name="is_active" required>
            <option value="1" @selected((string) old('is_active', $userGroup?->is_active ?? true) === '1')>Activo</option>
            <option value="0" @selected((string) old('is_active', $userGroup?->is_active ?? true) === '0')>Inactivo</option>
        </select>
    </label>
    <label class="span-two">Descripci&oacute;n
        <textarea name="description" maxlength="255" rows="4" placeholder="Describa el equipo o alcance del grupo">{{ old('description', $userGroup?->description) }}</textarea>
    </label>
</div>

@if ($userGroup?->exists && $userGroup->users->isNotEmpty())
    <div class="span-two">
        <h3>Miembros actuales ({{ $userGroup->users->count() }})</h3>
        <p class="muted">{{ $userGroup->users->pluck('name')->join(', ') }}</p>
    </div>
@endif

<div class="form-actions">
    <a class="button button-secondary" href="{{ route('user-groups.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
