<div class="form-grid two-cols">
<label>Nombre *<input type="text" name="nombre" value="{{ old('nombre',$donante?->nombre) }}" maxlength="255" required autofocus></label>
<label>Estado *<select name="estatus" required><option value="1" @selected((string)old('estatus',$donante?->estatus ?? true)==='1')>Activo</option><option value="0" @selected((string)old('estatus',$donante?->estatus ?? true)==='0')>Inactivo</option></select></label>
<label class="span-two">Enlace institucional<input type="url" name="enlaces" value="{{ old('enlaces',$donante?->enlaces) }}" maxlength="255" placeholder="https://ejemplo.org"><small>Opcional. Ingrese una direcci&oacute;n completa que comience con https://</small></label>
</div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('donantes.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
