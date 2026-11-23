@can('crear buzon')
<section class="content-card buzon-share-card" data-buzon-public-link data-asonacop-logo="{{ asset('assets/images/asonacop/logo.png') }}?v={{ @filemtime(public_path('assets/images/asonacop/logo.png')) ?: 1 }}" aria-labelledby="buzon-share-title">
    <div class="buzon-share-heading">
        <div class="buzon-share-intro"><span class="buzon-share-icon" aria-hidden="true"><i class="ri-share-line"></i></span><div>
            <h2 id="buzon-share-title">Enlace público del Buzón</h2>
            <p>Reciba opiniones de su proyecto compartiendo un enlace o un código QR.</p>
        </div></div>
        <span class="buzon-share-badge"><i class="ri-lock-line" aria-hidden="true"></i> Solo permite enviar opiniones</span>
    </div>
    <div class="buzon-share-layout">
        <div class="buzon-share-controls">
            <div class="buzon-share-project-row">
                <label class="buzon-share-field">Proyecto para compartir<select id="buzon-share-project" data-public-project><option value="">Seleccione un proyecto</option>
                    @foreach($publicLinkProjects as $item)<option value="{{ $item['project']->id }}" data-project-code="{{ $item['project']->codigo }}" data-project-title="{{ $item['project']->nombre_alias ?: $item['project']->descripcion }}" data-donor-name="{{ $item['project']->donante?->nombre ?: 'Donante no disponible' }}" data-project-logo="{{ $item['logo_url'] }}" data-public-url="{{ $item['url'] }}">{{ $item['project']->codigo }} — {{ $item['project']->nombre_alias ?: $item['project']->descripcion }} · {{ $item['project']->donante?->nombre ?: 'Donante no disponible' }}</option>@endforeach
                </select></label>
                <div class="buzon-share-donor" data-public-donor-preview aria-live="polite" hidden></div>
            </div>
            <p class="buzon-share-help">El enlace y el QR corresponden únicamente al proyecto seleccionado.</p>
            <div class="buzon-share-link" data-public-link-panel hidden>
                <label class="buzon-share-field">Enlace público de este proyecto<input type="url" readonly data-public-link-input aria-label="Enlace público del proyecto"></label>
                <div class="buzon-share-actions"><button class="button button-primary" type="button" data-public-link-copy><i class="ri-file-copy-line" aria-hidden="true"></i> Copiar enlace</button><a class="button button-secondary" data-public-link-open target="_blank" rel="noopener noreferrer"><i class="ri-external-link-line" aria-hidden="true"></i> Abrir formulario público</a></div>
            </div>
            <p class="buzon-share-status" data-public-link-status role="status" aria-live="polite"></p>
            @if($publicLinkProjects->isEmpty())<p>No tiene proyectos activos disponibles para compartir.</p>@endif
            <p class="buzon-share-privacy"><i class="ri-shield-check-line" aria-hidden="true"></i><span>Las personas pueden responder sin iniciar sesión. No podrán ver otras opiniones ni información interna de SIA.</span></p>
        </div>
        <aside class="buzon-share-preview" aria-label="Código QR del proyecto">
            <h3><i class="ri-qr-code-line" aria-hidden="true"></i> Código QR</h3>
            <div class="buzon-share-placeholder" data-public-qr-empty><i class="ri-qr-code-line" aria-hidden="true"></i><p>Seleccione un proyecto<br>para generar su QR.</p></div>
            <figure class="buzon-public-qr" data-public-qr-panel hidden>
                <canvas data-public-qr-canvas role="img" aria-label="Código QR del enlace público del proyecto"></canvas>
                <figcaption>Descargue la imagen para compartirla o imprimirla.</figcaption>
                <a class="button button-primary" data-public-qr-download download><i class="ri-download-2-line" aria-hidden="true"></i> Descargar QR (PNG)</a>
            </figure>
        </aside>
    </div>
    <div class="buzon-share-footer"><i class="ri-information-line" aria-hidden="true"></i><small>Las opiniones recibidas respetan los permisos del Buzón y el alcance de su grupo de trabajo. No se agregan a beneficiarios.</small></div>
    @if(in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true))<p class="buzon-share-local"><i class="ri-computer-line" aria-hidden="true"></i><span>Está trabajando en local. Para compartir un enlace o QR con otras personas, genérelo desde el sitio publicado de SIA.</span></p>@endif
</section>
@endcan
@can('crear buzon')@push('scripts')<script src="{{ asset('js/buzon-share-project.js') }}?v={{ filemtime(public_path('js/buzon-share-project.js')) }}"></script><script src="{{ asset('vendor/qrcodegen/qrcodegen.js') }}?v={{ filemtime(public_path('vendor/qrcodegen/qrcodegen.js')) }}"></script><script src="{{ asset('js/buzon-public-link.js') }}?v={{ filemtime(public_path('js/buzon-public-link.js')) }}"></script>@endpush @endcan
