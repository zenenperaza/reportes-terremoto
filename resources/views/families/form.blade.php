@extends('layouts.app')
@section('title',($familyRecord->exists ? 'Familia '.$familyRecord->name : 'Nueva familia').' | SIA')
@include('cases._assets')
@section('content')
<div class="case-module">
<form id="case-editor" class="case-editor" data-read-only="{{ $readOnly ? 'true' : 'false' }}" action="{{ $familyRecord->exists ? route('families.update',$familyRecord) : route('families.store') }}" method="post" autocomplete="off">
    @if(!$readOnly) @csrf @if($familyRecord->exists) @method('PUT') <input type="hidden" name="version" value="{{ old('version',$familyRecord->version) }}"> @endif @endif
    <header class="case-heading"><div><span class="case-eyebrow">GESTIÓN DE CASOS · FAMILIAS</span><h1>{{ $familyRecord->exists ? $familyRecord->name : 'Nueva familia' }}</h1><p>{{ $familyRecord->reference }}</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('families.index') }}">Volver</a>@if($readOnly) @can('update',$familyRecord)<a class="btn btn-primary" href="{{ route('families.edit',$familyRecord) }}">Editar</a>@endcan @else<button type="submit" class="btn btn-primary">Guardar familia</button>@endif</div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="case-surface p-4 mb-4"><h2 class="mb-4">Información de la familia</h2><div class="row g-4">
        <x-case-field name="name" label="Nombre de la familia" :value="$familyRecord->name" :required="true" :read-only="$readOnly" />
        <x-case-field name="registered_on" label="Fecha de registro" type="date" :value="$familyRecord->registered_on?->format('Y-m-d')" :required="true" :read-only="$readOnly" />
        <x-case-field name="restricted" label="Acceso restringido (requiere permiso VBG)" type="select" :value="(int)$familyRecord->restricted" :options="[0=>'No',1=>'Sí']" :required="true" :read-only="$readOnly" />
        @foreach(config('case-forms.family_fields') as $key=>$field)<x-case-dynamic-field :path="'details.'.$key" :field="$field" :value="data_get($familyRecord->details,$key)" :read-only="$readOnly" />@endforeach
    </div></section>
    <section class="case-surface p-4 mb-4"><p class="text-muted">Registre solo información familiar necesaria. No copie evaluaciones ni detalles confidenciales de casos individuales.</p>
        @if($familyRecord->exists)<p><strong>Integrantes registrados:</strong> {{ count($familyRecord->members ?? []) }}</p>@endif
        @include('cases._repeater',['path'=>'members','repeat'=>['label'=>'Integrantes de la familia','fields'=>config('case-forms.member_fields')],'rows'=>$familyRecord->members ?? []])
    </section>
    @if($familyRecord->exists)<section class="case-surface p-4"><h2 class="mb-3">Casos asociados</h2><p class="text-muted">Solo se muestran los casos que tiene permiso de consultar. Asocie un expediente desde «Registro de Familia».</p>@forelse($cases as $case)<a class="case-related" href="{{ route('cases.show',$case) }}">{{ $case->full_name }} <small>{{ $case->reference }}</small></a>@empty<p>No hay casos asociados visibles.</p>@endforelse</section>@endif
    @if(!$readOnly)<input type="hidden" name="form_complete" value="1">@endif
</form>
</div>
@endsection
