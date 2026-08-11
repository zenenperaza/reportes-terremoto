<div class="form-grid two-cols">
<label>Código *<input type="text" name="codigo" value="{{ old('codigo',$actividad?->codigo) }}" maxlength="50" required autofocus></label>
<label class="span-two">Descripción *<textarea name="descripcion" rows="4" maxlength="255" required>{{ old('descripcion',$actividad?->descripcion) }}</textarea></label>
</div><div class="form-actions"><a class="button button-secondary" href="{{ route('actividades.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
