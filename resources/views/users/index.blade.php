@extends('layouts.app')
@section('title', 'Usuarios | Respuesta ASONACOP')

@section('content')
<section class="page-heading">
    <div><p class="eyebrow">Administraci&oacute;n</p><h1>Usuarios del sistema</h1><p class="muted">Cree y administre las cuentas encargadas de registrar las actividades.</p></div>
    <a class="button button-primary" href="{{ route('users.create') }}">+ Nuevo usuario</a>
</section>

<section class="content-card users-table-card">
    <div class="users-table-card__header">
        <div><h2>Usuarios registrados</h2><p>Consulte, ordene y filtre las cuentas del sistema.</p></div>
        <span class="users-count">{{ $users->count() }} {{ $users->count() === 1 ? 'usuario' : 'usuarios' }}</span>
    </div>
    @if ($users->isEmpty())
        <div class="empty-state"><p>No hay usuarios registrados.</p></div>
    @else
    <div class="table-wrap users-table-wrap"><table id="users-table" class="display nowrap users-datatable" style="width:100%">
        <thead><tr><th class="user-details-heading"></th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Proyectos</th><th>Ubicaciones asignadas</th><th>Registros</th><th>Beneficiarios</th><th>Creado</th><th>Acciones</th></tr></thead>
        <tbody>
        @foreach ($users as $managedUser)
        <tr>
            <td class="user-details-control"><button type="button" aria-label="Mostrar detalles" title="Mostrar detalles">+</button></td>
            <td><div class="user-cell"><img src="{{ $managedUser->profile_photo_url }}" alt="Foto de {{ $managedUser->name }}"><div><strong>{{ $managedUser->name }}</strong>@if ($managedUser->is(auth()->user()))<small>Usted</small>@endif</div></div></td>
            <td>{{ $managedUser->email }}</td>
            <td><span class="role role-{{ $managedUser->role }}">{{ $roleLabels[$managedUser->role] ?? $managedUser->role }}</span></td>
            <td><span class="status {{ $managedUser->is_active ? 'status-active' : 'status-inactive' }}">{{ $managedUser->is_active ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="projects-cell" title="{{ $managedUser->projects->map(fn ($project) => $project->codigo.' — '.$project->descripcion)->join(' | ') }}">{{ $managedUser->projects->pluck('codigo')->join(', ') ?: 'Sin asignación' }}</td>
            <td>
                @if ($managedUser->isAdministrator() || $managedUser->countrywide_access) Todo el pa&iacute;s
                @elseif ($managedUser->assignedStates->isEmpty() && $managedUser->assignedMunicipalities->isEmpty()) <span class="muted">Sin asignaci&oacute;n</span>
                @else
                    {{ $managedUser->assignedStates->pluck('name')->join(', ') }}
                    @if ($managedUser->assignedStates->isNotEmpty() && $managedUser->assignedMunicipalities->isNotEmpty())<br>@endif
                    <small>{{ $managedUser->assignedMunicipalities->map(fn ($municipality) => $municipality->state->name.' / '.$municipality->name)->join(', ') }}</small>
                @endif
            </td>
            <td>{{ number_format($managedUser->reports_count) }}</td><td>{{ number_format($managedUser->beneficiaries_count) }}</td><td>{{ $managedUser->created_at->format('d/m/Y') }}</td>
            <td class="row-actions">
                <a class="btn btn-warning btn-icon waves-effect waves-light" href="{{ route('users.edit', $managedUser) }}" title="Editar usuario" aria-label="Editar usuario"><i class="ri-pencil-fill"></i></a>
                @if (! $managedUser->is(auth()->user()) && $managedUser->beneficiaries_count === 0)
                <form action="{{ route('users.destroy', $managedUser) }}" method="post" onsubmit="return confirm('¿Eliminar esta cuenta?');">@csrf @method('DELETE')<button class="btn btn-danger btn-icon waves-effect waves-light" type="submit" title="Eliminar usuario" aria-label="Eliminar usuario"><i class="ri-delete-bin-line"></i></button></form>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
@endsection

@push('scripts')
<script src="{{ asset('vendor/datatables/dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/jszip.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/pdfmake.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/vfs_fonts.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.html5.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.print.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('users-table');
    if (!table || typeof DataTable === 'undefined') return;
    const usersDataTable = new DataTable(table, {
        pageLength: 10, lengthMenu: [[10,25,50,-1],[10,25,50,'Todos']], order: [[1,'asc']], autoWidth: false,
        layout: {
            topStart: ['pageLength', { buttons: [
                {extend:'copyHtml5',text:'Copiar',title:'Usuarios del sistema',exportOptions:{columns:[1,2,3,4,5,6,7,8,9]}},
                {extend:'csvHtml5',text:'CSV',title:'Usuarios del sistema',exportOptions:{columns:[1,2,3,4,5,6,7,8,9]}},
                {extend:'excelHtml5',text:'Excel',title:'Usuarios del sistema',exportOptions:{columns:[1,2,3,4,5,6,7,8,9]}},
                {extend:'pdfHtml5',text:'PDF',title:'Usuarios del sistema',orientation:'landscape',pageSize:'LEGAL',exportOptions:{columns:[1,2,3,4,5,6,7,8,9]}},
                {extend:'print',text:'Imprimir',title:'Usuarios del sistema',exportOptions:{columns:[1,2,3,4,5,6,7,8,9]}}
            ]}],
            topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging'
        },
        columnDefs: [
            {targets:0,orderable:false,searchable:false,className:'dt-body-center user-details-control'},
            {targets:2,className:'users-col-email'}, {targets:3,className:'users-col-role'},
            {targets:4,className:'users-col-status'}, {targets:5,className:'users-col-projects'},
            {targets:6,className:'users-col-locations'}, {targets:[7,8],className:'dt-body-center users-col-count'},
            {targets:9,className:'users-col-created'},
            {targets:10,orderable:false,searchable:false,className:'users-col-actions dt-head-left dt-body-left'}
        ],
        language: {search:'Buscar:',lengthMenu:'Mostrar _MENU_ registros',info:'Mostrando _START_ a _END_ de _TOTAL_ usuarios',infoEmpty:'Mostrando 0 usuarios',infoFiltered:'(filtrado de _MAX_ usuarios)',zeroRecords:'No se encontraron usuarios',emptyTable:'No hay usuarios registrados',paginate:{first:'Primero',previous:'Anterior',next:'Siguiente',last:'Último'}}
    });

    table.addEventListener('click', function (event) {
        const control = event.target.closest('.user-details-control');
        if (!control || !control.closest('tbody')) return;
        const tr = control.closest('tr');
        const row = usersDataTable.row(tr);
        const button = control.querySelector('button');
        if (row.child.isShown()) {
            row.child.hide(); tr.classList.remove('details-open');
            if (button) { button.textContent = '+'; button.setAttribute('aria-label', 'Mostrar detalles'); }
            return;
        }
        const data = row.data();
        row.child('<div class="user-responsive-details">' +
            '<div><span>Correo</span><strong>' + data[2] + '</strong></div>' +
            '<div><span>Rol</span><strong>' + data[3] + '</strong></div>' +
            '<div><span>Estado</span><strong>' + data[4] + '</strong></div>' +
            '<div><span>Proyectos</span><strong>' + data[5] + '</strong></div>' +
            '<div><span>Ubicaciones</span><strong>' + data[6] + '</strong></div>' +
            '<div><span>Registros</span><strong>' + data[7] + '</strong></div>' +
            '<div><span>Beneficiarios</span><strong>' + data[8] + '</strong></div>' +
            '<div><span>Creado</span><strong>' + data[9] + '</strong></div></div>').show();
        tr.classList.add('details-open');
        if (button) { button.textContent = '−'; button.setAttribute('aria-label', 'Ocultar detalles'); }
    });
});
</script>
@endpush
