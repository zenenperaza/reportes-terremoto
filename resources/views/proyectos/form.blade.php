<div class="form-grid two-cols">
<label>Donante *<select name="donante_id" required><option value="">Seleccione un donante</option>@foreach($donantes as $donante)<option value="{{ $donante->id }}" @selected((string)old('donante_id',$proyecto?->donante_id)===(string)$donante->id)>{{ $donante->nombre }}{{ $donante->estatus?'':' (Inactivo)' }}</option>@endforeach</select></label>
<label>Estado *<select name="estatus" required><option value="1" @selected((string)old('estatus',$proyecto?->estatus ?? true)==='1')>Activo</option><option value="0" @selected((string)old('estatus',$proyecto?->estatus ?? true)==='0')>Inactivo</option></select></label>
<label>C&oacute;digo *<input type="text" name="codigo" value="{{ old('codigo',$proyecto?->codigo) }}" maxlength="50" required></label>
<label>Descripci&oacute;n *<input type="text" name="descripcion" value="{{ old('descripcion',$proyecto?->descripcion) }}" maxlength="255" required></label>
<label>Fecha de inicio<input type="date" name="inicio" value="{{ old('inicio',$proyecto?->inicio?->format('Y-m-d')) }}"></label>
<label>Fecha de finalizaci&oacute;n<input type="date" name="fin" value="{{ old('fin',$proyecto?->fin?->format('Y-m-d')) }}"></label>
</div><div class="form-actions"><a class="button button-secondary" href="{{ route('proyectos.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
