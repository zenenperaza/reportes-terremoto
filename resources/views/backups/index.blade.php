@extends('layouts.app')

@section('title', 'Respaldos | Respuesta ASONACOP')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/backups.css') }}">
@endpush

@section('content')
<section class="page-heading compact-heading backup-heading">
    <div><p class="eyebrow">Administraci&oacute;n</p><h1>Respaldos</h1><p class="muted">Genere y administre copias comprimidas de la base de datos.</p></div>
    @can('generar respaldos')
        <form method="post" action="{{ route('backups.store') }}" id="create-backup-form">@csrf
            <button class="btn btn-primary" type="submit"><i class="ri-database-2-line me-1"></i> Generar respaldo</button>
        </form>
    @endcan
</section>

<section class="alert alert-info backup-notice" role="note">
    <i class="ri-shield-check-line"></i>
    <div><strong>Almacenamiento privado</strong><p>Los respaldos no son p&uacute;blicos. Cada operaci&oacute;n est&aacute; protegida por los permisos asignados al usuario.</p></div>
</section>

<section class="alert alert-success backup-notice" role="status">
    <i class="ri-time-line"></i>
    <div>
        <strong>Respaldo autom&aacute;tico diario activo</strong>
        <p>
            Se genera con el primer acceso autenticado de cada d&iacute;a y conserva los archivos de los &uacute;ltimos {{ $retentionDays }} d&iacute;as.
            @if($automaticBackupLastAt)
                &Uacute;ltima ejecuci&oacute;n: {{ $automaticBackupLastAt->timezone(config('app.timezone'))->format('d/m/Y h:i A') }}.
            @else
                La primera ejecuci&oacute;n est&aacute; pendiente.
            @endif
        </p>
    </div>
</section>

<section class="card backup-card">
    <div class="card-header d-flex align-items-center justify-content-between"><div><h2 class="card-title mb-1">Respaldos disponibles</h2><p class="text-muted mb-0">{{ $backups->count() }} {{ $backups->count() === 1 ? 'archivo almacenado' : 'archivos almacenados' }}</p></div><span class="badge bg-primary-subtle text-primary fs-13">SQL comprimido</span></div>
    <div class="card-body p-0">
        @if($backups->isEmpty())
            <div class="backup-empty"><i class="ri-database-2-line"></i><h3>No hay respaldos</h3><p>Genere el primer respaldo de la base de datos.</p></div>
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0 backup-table">
                <thead class="table-light"><tr><th>Archivo</th><th>Fecha y hora</th><th>Tama&ntilde;o</th><th class="text-start">Acciones</th></tr></thead>
                <tbody>@foreach($backups as $backup)<tr>
                    <td><div class="backup-file"><span><i class="ri-file-zip-line"></i></span><div><strong>{{ $backup['name'] }}</strong><small>Base de datos completa</small></div></div></td>
                    <td data-order="{{ $backup['modified_at'] }}">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['modified_at'])->format('d/m/Y h:i A') }}</td>
                    <td>{{ number_format($backup['size'] / 1048576, 2, ',', '.') }} MB</td>
                    <td><div class="backup-actions">
                        @can('descargar respaldos')<a class="btn btn-soft-primary btn-icon waves-effect waves-light" href="{{ route('backups.download', $backup['name']) }}" title="Descargar" aria-label="Descargar respaldo"><i class="ri-download-2-line"></i></a>@endcan
                        @can('eliminar respaldos')<form method="post" action="{{ route('backups.destroy', $backup['name']) }}" class="delete-backup-form">@csrf @method('DELETE')<button class="btn btn-soft-danger btn-icon waves-effect waves-light" type="submit" title="Eliminar" aria-label="Eliminar respaldo"><i class="ri-delete-bin-line"></i></button></form>@endcan
                    </div></td>
                </tr>@endforeach</tbody>
            </table></div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
document.getElementById('create-backup-form')?.addEventListener('submit', event => {
    const button = event.currentTarget.querySelector('button');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Generando...';
});
document.querySelectorAll('.delete-backup-form').forEach(form => form.addEventListener('submit', event => {
    if (!confirm('¿Eliminar definitivamente este respaldo?')) event.preventDefault();
}));
</script>
@endpush
