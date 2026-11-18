@extends('layouts.app')
@section('title', 'Buzón | SIA')
@push('styles')<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">@endpush
@section('content')
@php($schema = \App\Models\BuzonEntry::class)
<section class="page-heading">
    <div><p class="eyebrow">Opiniones y encuestas</p><h1>Buzón</h1><p class="muted">{{ $schema::INDICATOR_CODE }} · {{ $schema::INDICATOR_NAME }}</p></div>
    <div class="buzon-entry-actions"><a class="button button-secondary" href="{{ route('buzon.lists') }}">Consultar listados</a>
        @can('crear buzon')<a class="button button-primary" href="{{ route('buzon.create', count($filters['project_ids']) === 1 ? ['proyecto_id' => $filters['project_ids'][0]] : []) }}">+ Registrar opinión</a>@endcan
    </div>
</section>
@can('crear buzon')
<section class="content-card buzon-share-card" data-buzon-public-link aria-labelledby="buzon-share-title">
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
<section class="content-card">
    <h2>Consultar opiniones</h2>
    <form method="get" action="{{ route('buzon.index') }}" class="form-grid two-cols" data-buzon-filters>
        <label class="span-two">Proyectos
            <select name="project_ids[]" id="buzon-project-filter" multiple data-placeholder="Todos los proyectos disponibles">
                @foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array((string) $project->id, array_map('strval', $filters['project_ids']), true))>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}{{ $project->estatus ? '' : ' (Inactivo)' }}</option>@endforeach
            </select><small>Seleccione uno o varios. Sin selección se consultan todos sus proyectos disponibles.</small>
        </label>
        <label>Fecha de la opinión desde<input type="date" name="from" id="buzon-from" value="{{ $filters['from'] }}"></label>
        <label>Fecha de la opinión hasta<input type="date" name="to" id="buzon-to" value="{{ $filters['to'] }}"></label>
        <label>Tipo de opinión<select name="opinion"><option value="">Todas</option>@foreach($schema::OPINIONS as $value => $label)<option value="{{ $value }}" @selected($filters['opinion'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Tipo de retroalimentación<select name="feedback_type"><option value="">Todos</option>@foreach($schema::FEEDBACK_TYPES as $value => $label)<option value="{{ $value }}" @selected($filters['feedback_type'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="form-actions span-two"><a class="button button-secondary" href="{{ route('buzon.index') }}">Limpiar filtros</a><button class="button button-primary" type="submit">Consultar Buzón</button></div>
    </form>
</section>
<div class="buzon-summary">
    <section class="content-card"><p>Formularios recibidos</p><strong>{{ number_format($counts->sum()) }}</strong></section>
    @foreach($schema::OPINIONS as $value => $label)<section class="content-card"><p>{{ $label }}</p><strong>{{ number_format($counts[$value] ?? 0) }}</strong></section>@endforeach
</div>
<section class="content-card">
    <h2>Opiniones registradas</h2><p class="muted">Cada fila es un formulario del Buzón. No representa personas únicas y no se suma al informe de beneficiarios. Los totales corresponden a los filtros aplicados.</p>
    <div class="table-wrap"><table class="buzon-table"><thead><tr><th>Fecha de opinión</th><th>Proyecto</th><th>Lugar</th><th>Opinión</th><th>Retroalimentación</th><th>Nombre</th><th>Usuario</th><th>Origen</th><th>Acciones</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr>
            <td>{{ $entry->opinion_date->format('d/m/Y') }}</td><td><strong>{{ $entry->proyecto->codigo }}</strong><br>{{ $entry->proyecto->nombre_alias ?: $entry->proyecto->descripcion }}</td>
            <td>{{ $entry->place_name }}</td><td>{{ $schema::OPINIONS[$entry->opinion] }}</td><td>{{ $schema::FEEDBACK_TYPES[$entry->feedback_type] }}</td>
            <td>{{ $entry->full_name ?: 'Anónimo / Sin nombre' }}</td>
            <td>@if($entry->submitted_via_public_link)
                Externo (sin usuario)<small class="d-block muted">Responsable del enlace: {{ $entry->publicRecipient?->name ?: 'Usuario no disponible' }}</small>
                @else{{ $entry->creator?->name ?: 'Usuario no disponible' }}@endif
            </td>
            <td>{{ $entry->submitted_via_public_link ? 'Enlace externo' : 'Interno' }}</td><td>
                <div class="buzon-entry-actions"><a href="{{ route('buzon.show', $entry) }}">Ver formulario</a>
                    @if(in_array($entry->buzon_list_id, $visibleListIds, true))<a href="{{ route('buzon.lists.show', $entry->buzon_list_id) }}">Listado #{{ $entry->buzon_list_id }}</a>@endif
                    @if(in_array($entry->id, $manageableEntryIds, true))
                        @can('editar buzon')<a href="{{ route('buzon.edit', $entry) }}">Editar</a>@endcan
                    @endif
                    @include('buzon.partials.delete-button', ['canManageEntry' => in_array($entry->id, $manageableEntryIds, true)])
                </div>
            </td>
        </tr>@empty<tr><td colspan="9">No hay formularios del Buzón que coincidan con los filtros.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $entries->links() }}</div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
@can('crear buzon')@push('scripts')<script src="{{ asset('js/buzon-share-project.js') }}?v={{ filemtime(public_path('js/buzon-share-project.js')) }}"></script><script src="{{ asset('vendor/qrcodegen/qrcodegen.js') }}?v={{ filemtime(public_path('vendor/qrcodegen/qrcodegen.js')) }}"></script><script src="{{ asset('js/buzon-public-link.js') }}?v={{ filemtime(public_path('js/buzon-public-link.js')) }}"></script>@endpush @endcan
