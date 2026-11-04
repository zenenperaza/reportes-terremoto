        @foreach([
            ['disability', 'Personas con discapacidad', $summary['disability'], 'ri-wheelchair-line', 'info'],
            ['pregnancy', 'Embarazadas o lactantes', $summary['pregnancy'], 'ri-parent-line', 'success'],
            ['ethnicity', 'Personas indígenas / etnia', $summary['ethnicity'], 'ri-earth-line', 'indigo'],
        ] as [$key, $label, $value, $icon, $tone])
            <div class="general-kpi-column">
                <article class="card general-kpi-card" data-condition-card="{{ $key }}">
                    <div class="card-body">
                        <div><p>{{ $label }}</p><strong>{{ number_format($value) }}</strong></div>
                        <span class="general-kpi-icon tone-{{ $tone }}"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    </div>
                </article>
            </div>
        @endforeach
