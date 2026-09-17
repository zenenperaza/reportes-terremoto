@php
    $repeatRows = $readOnly ? ($rows ?? []) : old($path, $rows ?? []);
    $repeatRows = is_array($repeatRows) ? array_filter($repeatRows, 'is_array') : [];
    $repeatName = preg_replace('/\.([^\.]+)/', '[$1]', $path);
@endphp
<div class="case-repeater mt-4" data-repeater data-next="{{ count($repeatRows) ? max(array_map('intval', array_keys($repeatRows))) + 1 : 0 }}">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h3 class="h5">{{ $repeat['label'] }}</h3>@if(!$readOnly)<button type="button" class="btn btn-primary btn-sm" data-add-row>+ Agregar</button>@endif</div>
    @if(!$readOnly)<input type="hidden" name="{{ $repeatName }}" value="">@endif
    <div data-rows>
        @foreach($repeatRows as $index => $row)
            <details class="case-repeat-row" data-row @if(!$readOnly) open @endif>
                <summary>{{ collect([$row['name'] ?? null, $row['subject'] ?? null, $row['service'] ?? null, $row['caregiver'] ?? null, $row['date'] ?? null])->first(fn($v)=>is_scalar($v) && filled($v)) ?: $repeat['label'].' '.$loop->iteration }}</summary>
                <div class="row g-4 p-3">@foreach($repeat['fields'] as $key => $field)<x-case-dynamic-field :path="$path.'.'.$index.'.'.$key" :field="$field" :value="$row[$key] ?? null" :read-only="$readOnly" :required="$path === 'members' && $key === 'name'" />@endforeach</div>
                @if(!$readOnly)<button type="button" class="btn btn-outline-danger btn-sm m-3" data-remove-row>Quitar esta fila</button>@endif
            </details>
        @endforeach
    </div>
    <p class="text-muted" data-empty @if(count($repeatRows)) hidden @endif>No hay {{ mb_strtolower($repeat['label']) }} registrados.</p>
    @error($path)<p class="text-danger">{{ $message }}</p>@enderror
    @if(!$readOnly)
        <template data-row-template><details class="case-repeat-row" data-row open><summary>Nuevo registro</summary><div class="row g-4 p-3">
            @foreach($repeat['fields'] as $key => $field)<x-case-dynamic-field :path="$path.'.__INDEX__.'.$key" :field="$field" :read-only="false" :required="$path === 'members' && $key === 'name'" />@endforeach
        </div><button type="button" class="btn btn-outline-danger btn-sm m-3" data-remove-row>Quitar esta fila</button></details></template>
    @endif
</div>
