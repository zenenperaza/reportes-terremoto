@extends('layouts.app')
@section('title', 'Gestión de casos')
@include('cases._assets')
@section('content')
<div class="case-module">
    <header class="case-heading">
        <div><span class="case-eyebrow">PROTECCIÓN Y ACOMPAÑAMIENTO</span><h1>Gestión de casos</h1><p>Expedientes de niños, adolescentes y adultos.</p></div>
        @can('create', \App\Models\CaseRecord::class)<a class="btn btn-primary" href="{{ route('cases.start') }}"><i class="ri-add-line" aria-hidden="true"></i> Nuevo caso</a>@endcan
    </header>
    <div class="case-metrics">
        <div><span>Casos accesibles</span><strong>{{ $counts->sum() }}</strong></div>
        <div><span>Riesgo alto</span><strong>{{ $counts['high'] ?? 0 }}</strong></div>
        <div><span>Por evaluar</span><strong>{{ $counts['pending'] ?? 0 }}</strong></div>
    </div>
    <div class="case-list-layout">
        <section class="case-surface case-results" aria-label="Listado de casos">
            <div class="case-surface-heading"><h2>Expedientes</h2><span class="text-muted">{{ $cases->total() }} resultados</span></div>
            <div class="table-responsive">
                <table class="table align-middle case-table"><thead><tr><th>Código / Documento</th><th>Nombre</th><th>Edad</th><th>Sexo</th><th>Registro</th><th>Responsable</th><th>Riesgo</th><th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>
                @forelse($cases as $case)
                    <tr>
                        <td><a class="case-reference" href="{{ route('cases.show', $case) }}">{{ $case->reference }}</a><small>{{ $case->document_number ?: 'Sin documento registrado' }}</small></td>
                        <td><strong>{{ $case->full_name }}</strong><small>{{ config('case-management.types.'.$case->case_type) }} · {{ $case->state?->name ?: 'Sin ubicación' }}</small></td>
                        <td>{{ $case->age_label }}</td><td>{{ config('case-management.sexes.'.$case->sex) }}</td>
                        <td class="text-nowrap">{{ $case->registered_on->format('d/m/Y') }}</td><td>{{ $case->assignee?->name ?: 'Sin responsable' }}</td>
                        <td><span class="case-risk case-risk-{{ $case->risk_level }}">{{ config('case-management.risks.'.$case->risk_level) }}</span></td>
                        <td><a href="{{ route('cases.show', $case) }}" aria-label="Ver expediente {{ $case->reference }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="case-empty"><i class="ri-folder-open-line" aria-hidden="true"></i><h3>No hay casos para mostrar</h3><p>Pruebe otros filtros o registre un nuevo expediente si tiene permiso.</p></div></td></tr>
                @endforelse
                </tbody></table>
            </div>
            <div class="p-3">{{ $cases->links() }}</div>
        </section>
        <aside class="case-surface case-filters">
            <h2>Buscar y filtrar</h2>
            <form method="get" action="{{ route('cases.index') }}">
                <label class="form-label" for="search_by">Buscar por</label>
                <select class="form-select mb-3" name="search_by" id="search_by">@foreach(['name' => 'Nombre', 'id' => 'Código o documento (exacto)', 'phone' => 'Teléfono (exacto)'] as $key => $label)<option value="{{ $key }}" @selected(($filters['search_by'] ?? 'name') === $key)>{{ $label }}</option>@endforeach</select>
                <label for="case-search" class="visually-hidden">Texto de búsqueda</label><input class="form-control mb-3" id="case-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="200" placeholder="Buscar un expediente" autocomplete="off">
                @foreach(['case_type' => ['Tipo de caso', $types], 'risk_level' => ['Nivel de riesgo', config('case-management.risks')], 'sex' => ['Sexo', config('case-management.sexes')], 'state_id' => ['Estado', $states->pluck('name', 'id')], 'assigned_to' => ['Responsable', $assignees->pluck('name', 'id')]] as $field => [$label, $options])
                    <label class="form-label" for="filter-{{ $field }}">{{ $label }}</label><select class="form-select mb-3" name="{{ $field }}" id="filter-{{ $field }}"><option value="">Todos</option>@foreach($options as $key => $text)<option value="{{ $key }}" @selected((string)($filters[$field] ?? '') === (string)$key)>{{ $text }}</option>@endforeach</select>
                @endforeach
                <label class="form-label" for="filter-from">Registro desde</label><input class="form-control mb-3" type="date" name="from" id="filter-from" value="{{ $filters['from'] ?? '' }}">
                <label class="form-label" for="filter-to">Registro hasta</label><input class="form-control mb-3" type="date" name="to" id="filter-to" value="{{ $filters['to'] ?? '' }}">
                <div class="d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Aplicar</button><a class="btn btn-outline-secondary" href="{{ route('cases.index') }}">Limpiar</a></div>
            </form>
        </aside>
    </div>
</div>
@endsection
