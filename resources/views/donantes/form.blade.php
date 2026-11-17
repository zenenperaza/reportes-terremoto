@php
    $donorLogoUrl = \App\Support\DonorLogo::url($donante);
@endphp
@push('styles')<link rel="stylesheet" href="{{ asset('css/donor-logo.css') }}?v={{ filemtime(public_path('css/donor-logo.css')) }}">@endpush
<div class="form-grid two-cols">
<label>Nombre *<input type="text" name="nombre" value="{{ old('nombre',$donante?->nombre) }}" maxlength="255" required autofocus></label>
<label>Estado *<select name="estatus" required><option value="1" @selected((string)old('estatus',$donante?->estatus ?? true)==='1')>Activo</option><option value="0" @selected((string)old('estatus',$donante?->estatus ?? true)==='0')>Inactivo</option></select></label>
<label class="span-two">Enlace institucional<input type="url" name="enlaces" value="{{ old('enlaces',$donante?->enlaces) }}" maxlength="255" placeholder="https://ejemplo.org"><small>Opcional. Ingrese una direcci&oacute;n completa que comience con https://</small></label>
<section class="span-two donor-logo-section" aria-labelledby="donor-logo-title">
    <h3 id="donor-logo-title">Logo del donante (opcional)</h3>
    <p class="muted">Se mostrará en el centro del QR del Buzón de todos los proyectos asociados a este donante.</p>
    <div class="donor-logo-upload">
        @if($donorLogoUrl)<img class="donor-logo-preview" src="{{ $donorLogoUrl }}" alt="Logo actual del donante" width="96" height="96">@endif
        <div>
            <label>Imagen del logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG o WebP · Máximo 2 MB · Entre 32 y 2000 píxeles de ancho y alto. Se recomienda una imagen cuadrada y legible en tamaño pequeño.</small></label>
            @if($donorLogoUrl)<label class="donor-logo-remove"><input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))> Quitar logo actual</label><small>Si no selecciona una imagen nueva ni marca Quitar, se conserva el logo actual.</small>@endif
        </div>
    </div>
</section>
</div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('donantes.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
