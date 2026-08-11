<div class="form-grid two-cols">
<label>Nombre *<input type="text" name="nombre" value="{{ old('nombre',$servicio?->nombre) }}" maxlength="255" required autofocus></label>
<label class="span-two">Descripción<textarea name="descripcion" rows="4" maxlength="255">{{ old('descripcion',$servicio?->descripcion) }}</textarea></label>
</div><div class="form-actions"><a class="button button-secondary" href="{{ route('servicios.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
