@extends('layouts.app')
@section('title', 'Configuraciones | SIA')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/system-configuration.css') }}?v={{ filemtime(public_path('css/system-configuration.css')) }}">
@endpush
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Configuración</p>
        <h1>Configuraciones</h1>
        <p class="muted">Administre el período actual y el cierre de períodos anteriores.</p>
    </div>
</section>

<section class="content-card">
    <form method="post" action="{{ route('system-configuration.update') }}">
        @csrf
        @method('PUT')
        <fieldset class="border-0 p-0 m-0" aria-describedby="period-help">
            <legend class="float-none w-auto fs-5 mb-3">Período actual</legend>
            <div class="row g-3" style="max-width: 800px">
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="period-month">Mes</label>
                    <select class="form-select @error('period_month') is-invalid @enderror" id="period-month" name="period_month" required @error('period_month') aria-invalid="true" aria-describedby="period-month-error" @enderror>
                        @foreach($months as $number => $month)<option value="{{ $number }}" @selected((string) old('period_month', $period['month']) === (string) $number)>{{ $month }}</option>@endforeach
                    </select>
                    @error('period_month')<div class="invalid-feedback" id="period-month-error">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="period-year">Año</label>
                    <select class="form-select @error('period_year') is-invalid @enderror" id="period-year" name="period_year" required @error('period_year') aria-invalid="true" aria-describedby="period-year-error" @enderror>
                        @foreach($years as $year)<option value="{{ $year }}" @selected((string) old('period_year', $period['year']) === (string) $year)>{{ $year }}</option>@endforeach
                    </select>
                    @error('period_year')<div class="invalid-feedback" id="period-year-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <p class="muted mt-3" id="period-help">Seleccione el mes y el año y guarde la configuración. Este período se asigna a los registros nuevos; no modifica el período ni las fechas de registros anteriores. Los informes permiten filtrar por el período guardado.</p>
        </fieldset>
        <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i> Guardar configuraciones</button></div>
    </form>
</section>
<section class="content-card" aria-labelledby="period-history-title">
    <h2 id="period-history-title">Administrador de períodos</h2>
    <p class="muted mt-2">Marque «Cerrado» y guarde para bloquear cambios y eliminaciones de registros y beneficiarios, incluso para administradores. Desmarque la casilla y guarde para reabrir el período. Las consultas y exportaciones siguen disponibles.</p>
    <p class="muted">Si cierra el período actual, deberá reabrirlo o seleccionar otro período abierto para registrar nuevas atenciones.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Período</th><th>Registros</th><th>Fecha de cierre</th><th>Estado del período</th></tr></thead>
            <tbody>
                @foreach($periods as $entry)
                    <tr>
                        <td>{{ $entry['label'] }} @if($entry['current'])<span class="badge bg-primary ms-2">Actual</span>@endif</td>
                        <td><a href="{{ route('reports.index', ['reporting_period' => $entry['value']]) }}">{{ number_format($entry['count']) }}</a></td>
                        <td>{{ $entry['closed_at']?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                            <form method="post" action="{{ route('system-configuration.periods.update', $entry['value']) }}" class="d-flex align-items-center flex-wrap gap-3">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_closed" value="0">
                                <label class="period-close-control" for="closed-{{ $entry['value'] }}">
                                    <input type="checkbox" name="is_closed" value="1" id="closed-{{ $entry['value'] }}" @checked($entry['closed'])>
                                    <span class="period-close-caption"><i class="ri-lock-line" aria-hidden="true"></i> Cerrado<span class="visually-hidden">: {{ $entry['label'] }}</span></span>
                                </label>
                                <button type="submit" class="btn btn-sm btn-primary" aria-label="Guardar estado de {{ $entry['label'] }}">Guardar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($unassignedCount > 0)
        <p class="alert alert-warning mb-0">Hay {{ number_format($unassignedCount) }} registros sin período asignado. No pertenecen a ningún período y no se bloquean mediante estas casillas. No se les asignará un período automáticamente.</p>
    @endif
</section>
@endsection
