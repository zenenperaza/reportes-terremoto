@extends('layouts.app')
@section('title', 'Mantenimiento del sistema | Respuesta ASONACOP')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/system-maintenance.css') }}">
@endpush
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Configuración</p>
        <h1>Mantenimiento del sistema</h1>
        <p class="muted">Controle temporalmente el acceso de registradores y coordinadores mientras realiza cambios.</p>
    </div>
</section>

<section class="content-card maintenance-control">
    <div>
        <p class="eyebrow">Estado actual</p>
        <h2>{{ $maintenanceEnabled ? 'Sistema bloqueado' : 'Sistema disponible' }}</h2>
        <p>
            @if($maintenanceEnabled)
                Los registradores y coordinadores no pueden usar el sistema. Los administradores conservan el acceso.
            @else
                Todos los usuarios activos pueden ingresar y trabajar normalmente.
            @endif
        </p>
        <span class="status {{ $maintenanceEnabled ? 'status-inactive' : 'status-active' }}">
            {{ $maintenanceEnabled ? 'Mantenimiento activo' : 'Funcionamiento normal' }}
        </span>
    </div>

    <form id="maintenance-toggle-form" action="{{ route('system-maintenance.update') }}" method="post"
          data-enabling="{{ $maintenanceEnabled ? '1' : '0' }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="enabled" value="{{ $maintenanceEnabled ? 0 : 1 }}">
        <button class="button {{ $maintenanceEnabled ? 'button-primary' : 'button-danger' }}" type="submit">
            {{ $maintenanceEnabled ? 'Habilitar sistema' : 'Bloquear sistema' }}
        </button>
    </form>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('maintenance-toggle-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const enabling = this.dataset.enabling === '1';
        const result = await Swal.fire({
            icon: enabling ? 'question' : 'warning',
            title: enabling ? '¿Habilitar el sistema?' : '¿Bloquear el sistema?',
            text: enabling
                ? 'Los registradores y coordinadores podrán ingresar nuevamente.'
                : 'Los registradores y coordinadores perderán temporalmente el acceso. Los administradores podrán continuar trabajando.',
            showCancelButton: true,
            confirmButtonText: enabling ? 'Sí, habilitar' : 'Sí, bloquear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: enabling ? '#1c8b5c' : '#b42318',
            cancelButtonColor: '#647784',
            reverseButtons: true,
            focusCancel: !enabling,
        });

        if (result.isConfirmed) this.submit();
    });
</script>
@endpush
