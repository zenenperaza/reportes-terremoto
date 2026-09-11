<div class="form-grid two-cols">
<label>C&oacute;digo *<input type="text" name="codigo" value="{{ old('codigo',$indicador?->codigo) }}" maxlength="50" required autofocus></label>
<label>Unidad de conteo *<select name="unidad_conteo" required><option value="">Seleccione</option>@foreach($unidadesConteo as $unidad)<option value="{{ $unidad }}" @selected(old('unidad_conteo',$indicador?->unidad_conteo)===$unidad)>{{ $unidad }}</option>@endforeach</select></label>
<label class="span-two">Grupo de indicadores
    <select name="indicator_group_id">
        <option value="">Sin grupo asignado</option>
        @foreach($indicatorGroups as $group)
            <option value="{{ $group->id }}" @selected((string) old('indicator_group_id', $indicador?->indicator_group_id) === (string) $group->id)>{{ $group->sort_order }} &mdash; {{ $group->name }}</option>
        @endforeach
    </select>
    <small>El grupo define el bloque y el orden en que aparecer&aacute; al registrar una actividad.</small>
</label>
<label class="span-two">Nombre corto
    <input type="text" name="nombre_corto" value="{{ old('nombre_corto', $indicador?->nombre_corto) }}" maxlength="150" placeholder="Ej. NNA en actividades grupales">
    <small>Este nombre se mostrar&aacute; en las tarjetas de selecci&oacute;n al registrar una actividad.</small>
</label>
<label class="span-two">Nombre del indicador *<textarea name="descripcion" rows="4" maxlength="255" required>{{ old('descripcion',$indicador?->descripcion) }}</textarea></label>
<label>Espacio de coordinaci&oacute;n *<select name="espacio_coordinacion" required><option value="">Seleccione</option>@foreach($espacios as $espacio)<option value="{{ $espacio }}" @selected(old('espacio_coordinacion',$indicador?->espacio_coordinacion)===$espacio)>{{ $espacio }}</option>@endforeach</select></label>
<label>Edad desde *<input type="number" name="edad_desde" value="{{ old('edad_desde',$indicador?->edad_desde) }}" min="0" max="120" required></label>
<label>Edad hasta *<input type="number" name="edad_hasta" value="{{ old('edad_hasta',$indicador?->edad_hasta) }}" min="0" max="120" required></label>
</div><div class="form-actions"><a class="button button-secondary" href="{{ route('indicadores.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
