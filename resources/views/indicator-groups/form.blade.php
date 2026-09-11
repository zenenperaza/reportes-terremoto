<div class="form-grid two-cols">
    <label>Nombre del grupo *
        <input type="text" name="name" value="{{ old('name', $indicatorGroup?->name) }}" maxlength="120" required autofocus placeholder="Ej. Apoyo psicosocial (SMAPS)">
    </label>
    <label>N&uacute;mero de orden *
        <input type="number" name="sort_order" value="{{ old('sort_order', $indicatorGroup?->sort_order ?? 0) }}" min="0" max="9999" required>
    </label>
    <label class="span-two">Descripci&oacute;n
        <textarea name="description" maxlength="255" rows="4" placeholder="Explique brevemente qu&eacute; indicadores contiene este grupo">{{ old('description', $indicatorGroup?->description) }}</textarea>
    </label>
</div>

<div class="form-actions">
    <a class="button button-secondary" href="{{ route('indicator-groups.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
