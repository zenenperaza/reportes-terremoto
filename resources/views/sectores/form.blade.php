<div class="form-grid two-cols">
    <label>Código *
        <input type="text" name="codigo" value="{{ old('codigo', $sector?->codigo) }}" maxlength="50" required autofocus placeholder="Ej. PROT">
    </label>
    <label>Descripción *
        <input type="text" name="descripcion" value="{{ old('descripcion', $sector?->descripcion ?: $sector?->name) }}" maxlength="255" required placeholder="Ej. Protección de la niñez">
    </label>
</div>
<div class="form-actions">
    <a class="button button-secondary" href="{{ route('sectores.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
