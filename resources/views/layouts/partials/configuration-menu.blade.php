@php
    $configurationSections = [
        'sidebarConfigurationProjects' => [
            'label' => 'Proyectos', 'icon' => 'ri-folder-2-line',
            'links' => [
                ['Donantes', 'donantes.index', ['donantes.*']],
                ['Gestión de proyectos', 'proyectos.index', ['proyectos.*', 'sector-proyecto.*', 'indicador-proyecto.*', 'actividad-indicador.*', 'servicio-actividad.*']],
                ['Sectores', 'sectores.index', ['sectores.*']],
                ['Grupos de indicadores', 'indicator-groups.index', ['indicator-groups.*']],
                ['Indicadores', 'indicadores.index', ['indicadores.*']],
                ['Actividades', 'actividades.index', ['actividades.*']],
                ['Servicios', 'servicios.index', ['servicios.*']],
                ['Lugares', 'place-names.index', ['place-names.*'], auth()->user()->can('manejar lugares')],
            ],
        ],
        'sidebarConfigurationSettings' => [
            'label' => 'Configuraciones', 'icon' => 'ri-settings-3-line',
            'links' => [
                ['Bitácora', 'audit-logs.index', ['audit-logs.*']],
                ['Respaldos', 'backups.index', ['backups.*'], auth()->user()->canAny(['generar respaldos', 'descargar respaldos', 'eliminar respaldos'])],
                ['Configuraciones', 'system-configuration.index', ['system-configuration.*']],
                ['Mantenimiento', 'system-maintenance.index', ['system-maintenance.*']],
            ],
        ],
        'sidebarConfigurationUsers' => [
            'label' => 'Usuarios', 'icon' => 'ri-team-line',
            'links' => [
                ['Usuarios', 'users.index', ['users.*']],
                ['Grupos de usuarios', 'user-groups.index', ['user-groups.*']],
                ['Roles', 'roles.index', ['roles.*']],
                ['Permisos', 'permissions.index', ['permissions.*']],
            ],
        ],
    ];
@endphp
@foreach($configurationSections as $sectionId => $section)
    @php
        $links = collect($section['links'])->filter(fn ($link) => ($link[3] ?? true) && Route::has($link[1]));
        $sectionActive = $links->contains(fn ($link) => request()->routeIs(...$link[2]));
    @endphp
    @if($links->isNotEmpty())
        <li class="nav-item">
            <a class="nav-link configuration-group-toggle {{ $sectionActive ? 'active' : 'collapsed' }}" href="#{{ $sectionId }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $sectionActive ? 'true' : 'false' }}" aria-controls="{{ $sectionId }}">
                <i class="{{ $section['icon'] }}" aria-hidden="true"></i><span>{{ $section['label'] }}</span>
            </a>
            <div class="collapse configuration-submenu {{ $sectionActive ? 'show' : '' }}" id="{{ $sectionId }}" data-bs-parent="#sidebarConfiguration">
                <ul class="nav nav-sm flex-column">
                    @foreach($links as [$label, $route, $patterns])
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs(...$patterns)) aria-current="page" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
        </li>
    @endif
@endforeach
