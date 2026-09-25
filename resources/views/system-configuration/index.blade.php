@extends('layouts.app')
@section('title', 'Configuraciones | SIA')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Configuración</p>
        <h1>Configuraciones</h1>
        <p class="muted">Administre el período actual del sistema.</p>
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
            <p class="muted mt-3" id="period-help">Seleccione el mes y el año y guarde la configuración. Este ajuste no modifica las fechas de los registros ni aplica filtros automáticamente a los informes.</p>
        </fieldset>
        <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i> Guardar configuraciones</button></div>
    </form>
</section>
@endsection
