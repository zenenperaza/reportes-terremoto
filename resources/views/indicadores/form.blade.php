<div class="form-grid two-cols">
<label>C&oacute;digo *<input type="text" name="codigo" value="{{ old('codigo',$indicador?->codigo) }}" maxlength="50" required autofocus></label>
<label>Unidad de conteo *<input type="text" name="unidad_conteo" value="{{ old('unidad_conteo',$indicador?->unidad_conteo) }}" maxlength="100" placeholder="Personas" required></label>
<label class="span-two">Nombre del indicador *<textarea name="descripcion" rows="4" maxlength="255" required>{{ old('descripcion',$indicador?->descripcion) }}</textarea></label>
<label>Espacio de coordinaci&oacute;n *<select name="espacio_coordinacion" required><option value="">Seleccione</option>@foreach($espacios as $espacio)<option value="{{ $espacio }}" @selected(old('espacio_coordinacion',$indicador?->espacio_coordinacion)===$espacio)>{{ $espacio }}</option>@endforeach</select></label>
<label>Poblaci&oacute;n dirigida *<select name="poblacion_dirigida" required><option value="">Seleccione</option>@foreach($poblaciones as $poblacion)<option value="{{ $poblacion }}" @selected(old('poblacion_dirigida',$indicador?->poblacion_dirigida)===$poblacion)>{{ $poblacion }}</option>@endforeach</select></label>
</div><div class="form-actions"><a class="button button-secondary" href="{{ route('indicadores.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
