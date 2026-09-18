@extends('layouts.app')
@section('title', ($caseRecord->exists ? 'Expediente '.$caseRecord->reference : 'Nuevo caso').' | SIA')
@include('cases._assets')
@section('content')
@php
    $labels = config('case-management.fields') + ['family_record_id'=>'Grupo familiar','status'=>'Estado del caso'];
    $navigation = config('case-forms.navigation');
    $navigation['Información del registro']['marks'] = 'Marcas';
    $sections = collect($navigation)->flatMap(fn($items)=>$items)->all();
    $schema = config('case-forms.sections'); $formData = $caseRecord->form_data ?? [];
    $fieldGroups = [
        'identity'=>['full_name','document_type','document_number','birth_date','age_at_registration','sex','nationality','registered_on','case_type','proyecto_id'],
        'assignments'=>['assigned_to'], 'contact'=>['state_id','municipality_id','parish_id','address','phone','safe_contact'],
        'family_registration'=>['family_record_id'], 'family'=>['support_person','support_relationship','support_phone','family_notes'],
        'consent'=>['consent_status','consent_source','consent_date','share_services','share_reports','consent_notes'],
        'assessment'=>['presenting_needs','immediate_actions'], 'protection'=>['risk_level'], 'closure'=>['status'],
    ];
    $options = [
        'sex'=>config('case-management.sexes'), 'case_type'=>$types, 'risk_level'=>config('case-management.risks'), 'consent_status'=>config('case-management.consents'),
        'proyecto_id'=>$projects->mapWithKeys(fn($p)=>[$p->id=>$p->codigo.' — '.$p->descripcion])->all(),
        'assigned_to'=>$assignees->pluck('name','id')->all(), 'family_record_id'=>$families->mapWithKeys(fn($f)=>[$f->id=>$f->name.' — '.$f->reference])->all(),
        'state_id'=>$states->pluck('name','id')->all(), 'municipality_id'=>$municipalities->pluck('name','id')->all(), 'parish_id'=>$parishes->pluck('name','id')->all(),
        'share_services'=>[0=>'No',1=>'Sí'], 'share_reports'=>[0=>'No',1=>'Sí'], 'status'=>['open'=>'Abierto','closed'=>'Cerrado'],
    ];
    $options['consent_source'] = ['Persona'=>'Persona','Cuidador'=>'Cuidador','Otro'=>'Otro'];
    if(filled($caseRecord->consent_source)) { $options['consent_source'][$caseRecord->consent_source]=$caseRecord->consent_source; }
    $oldSource=old('consent_source'); if(is_string($oldSource) && filled($oldSource)) { $options['consent_source'][$oldSource]=$oldSource; }
    if ($caseRecord->assignee) { $options['assigned_to'][$caseRecord->assigned_to] = $caseRecord->assignee->name; }
    $longFields=['address','safe_contact','family_notes','consent_notes','presenting_needs','immediate_actions'];
    $dateFields=['birth_date','registered_on','consent_date'];
    $requiredFields=['full_name','sex','registered_on','case_type','consent_status','share_services','share_reports','risk_level'];
@endphp
<div class="case-module case-primero">
<form method="post" action="{{ $caseRecord->exists ? route('cases.update',$caseRecord) : route('cases.store') }}" id="case-editor" class="case-editor" autocomplete="off" data-initial-section="identity" data-read-only="{{ $readOnly ? 'true' : 'false' }}" data-municipalities-url="{{ url('/ubicaciones/estados') }}" data-parishes-url="{{ url('/ubicaciones/municipios') }}">
    @if(!$readOnly) @csrf @if($caseRecord->exists) @method('PUT') <input type="hidden" name="version" value="{{ old('version',$caseRecord->version) }}"> @endif @endif
    <header class="case-heading case-editor-heading">
        <div><h1>{{ $caseRecord->exists ? 'ID del Caso: '.$caseRecord->reference : 'Nuevo Caso' }}</h1>@if($caseRecord->exists)<span>{{ $caseRecord->full_name }}</span>@endif</div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary" data-jump="marks"><i class="ri-flag-line"></i> Marcas</button>
            <a class="btn btn-outline-secondary" href="{{ route('cases.index') }}">{{ $readOnly ? 'Volver' : 'Cancelar' }}</a>
            @if($readOnly) @can('update',$caseRecord)<a class="btn btn-primary" href="{{ route('cases.edit',$caseRecord) }}">Editar</a>@endcan
            @else<button class="btn btn-primary" type="submit">Guardar expediente</button>@endif
        </div>
    </header>
    <ol class="case-workflow" aria-label="Etapas del caso">@foreach(config('case-forms.stages') as $stage)<li class="{{ $loop->index === $caseRecord->workflow_stage ? 'active' : '' }}" @if($loop->index === $caseRecord->workflow_stage) aria-current="step" @endif><span>{{ $loop->iteration }}</span>{{ $stage }}</li>@endforeach</ol>
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>No se guardó el expediente. Revise los siguientes campos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <noscript><p class="alert alert-warning">Active JavaScript para usar las pestañas y agregar filas a los formularios repetibles.</p></noscript>
    <div class="case-editor-layout">
        <nav class="case-section-nav case-surface" aria-label="Secciones del expediente">
            @foreach($navigation as $group=>$items)<details class="case-nav-group" open><summary>{{ $group }}</summary>@foreach($items as $id=>$title)<a href="#section-{{ $id }}" class="case-section-link" data-section="{{ $id }}">{{ $title }}</a>@endforeach</details>@endforeach
        </nav>
        <div class="case-surface case-form-content">
            @foreach($sections as $id=>$title)
            <section class="case-panel" id="section-{{ $id }}" data-panel="{{ $id }}" aria-labelledby="heading-{{ $id }}">
                <div class="case-panel-heading"><h2 id="heading-{{ $id }}">{{ $title }}</h2></div>
                @if($id==='identity')<p class="text-muted">Registro para personas de cualquier edad. Los campos con * son obligatorios.</p>@endif
                @if($id==='consent')<p class="text-muted">Consentimiento y confidencialidad: registre quién autoriza, los usos permitidos y sus límites.</p>@endif
                @if($id==='contact' && !$readOnly)<p role="status" id="case-location-status"></p>@endif
                @if($id==='transfers')<p class="alert alert-info">Este formulario documenta las derivaciones realizadas. No envía información a otros sistemas ni cambia el responsable; las asignaciones internas se realizan en «Transferencias / Asignaciones».</p>@endif
                <div class="row g-4">
                    @foreach($fieldGroups[$id] ?? [] as $key)
                        @php
                            $value=$caseRecord->$key; if(in_array($key,$dateFields)){$value=$value?->format('Y-m-d');} if(is_bool($value)){$value=(int)$value;}
                            $locked=$readOnly || ($key==='assigned_to' && !auth()->user()->can('asignar casos')) || ($key==='status' && !auth()->user()->can('supervisar casos'));
                            $type=isset($options[$key])?'select':(in_array($key,$longFields)?'textarea':(in_array($key,$dateFields)?'date':($key==='age_at_registration'?'number':'text')));
                        @endphp
                        <x-case-field :name="$key" :label="$labels[$key]" :type="$type" :value="$value" :options="$options[$key] ?? []" :required="in_array($key,$requiredFields)" :read-only="$locked" :wide="in_array($key,$longFields) || $key==='full_name'" />
                    @endforeach
                    @foreach($schema[$id]['fields'] ?? [] as $key=>$field)<x-case-dynamic-field :path="'form_data.'.$id.'.'.$key" :field="$field" :value="data_get($formData,$id.'.'.$key)" :read-only="$readOnly" />@endforeach
                </div>
                @if(isset($schema[$id]['repeat'])) @include('cases._repeater',['path'=>'form_data.'.$id.'.rows','repeat'=>$schema[$id]['repeat'],'rows'=>data_get($formData,$id.'.rows',[])]) @endif
                @if($id==='family_registration')
                    <p class="text-muted mt-3">Una familia puede estar asociada a varios casos. La asociación no modifica los permisos del expediente.</p>
                    @can('create',App\Models\FamilyRecord::class)<a href="{{ route('families.create') }}" target="_blank" rel="noopener">Registrar una familia en otra pestaña</a><small class="d-block text-muted">Guarde los cambios antes de recargar para ver la nueva familia.</small>@endcan
                    @if($family)<a class="d-block mt-3" href="{{ route('families.show',$family) }}">Ver familia: {{ $family->name }}</a>@endif
                @endif
                @if($id==='family' && $family)
                    <p class="mt-4"><a href="{{ route('families.show',$family) }}">{{ $family->name }}</a></p>
                    @include('cases._repeater',['path'=>'linked_family_members','repeat'=>['label'=>'Integrantes de la familia','fields'=>config('case-forms.member_fields')],'rows'=>$family->members ?? [],'readOnly'=>true])
                @endif
                @if($id==='linked')
                    <p class="text-muted">Casos de la misma familia a los que tiene acceso.</p>
                    @forelse($relatedCases as $related)<a class="case-related" href="{{ route('cases.show',$related) }}">{{ $related->full_name }} <small>{{ $related->reference }}</small></a>@empty<p>No hay otros casos visibles asociados a esta familia.</p>@endforelse
                @endif
                @if($id==='approvals')
                    <p class="text-muted">Las aprobaciones se registran en Evaluación, Plan del caso y Cierre. Solo un supervisor puede modificarlas.</p>
                    @foreach(['assessment'=>'Evaluación','plan'=>'Plan del caso','closure'=>'Cierre'] as $approvalId=>$approvalLabel)
                        <div class="case-approval"><h3 class="h5">{{ $approvalLabel }}</h3><div class="row g-4">@foreach($schema[$approvalId]['fields'] as $key=>$field)@if($field['supervisor'])<x-case-dynamic-field :path="'approval_summary.'.$approvalId.'.'.$key" :field="$field" :value="data_get($formData,$approvalId.'.'.$key)" :read-only="true" />@endif @endforeach</div></div>
                    @endforeach
                @endif
                @if($id==='incidents')<p>{{ count(data_get($formData,'incident_details.rows',[]) ?: []) }} incidentes documentados en este expediente.</p><button type="button" class="btn btn-outline-primary" data-jump="incident_details">Ver detalles del incidente</button>@endif
                @if($id==='referrals')<p>Consulte y documente la agencia destinataria, el consentimiento y el resultado de cada derivación.</p><button type="button" class="btn btn-outline-primary" data-jump="transfers">Ver derivaciones y transferencias</button>@endif
                @if($id==='changes') @include('cases._events',['actions'=>['created','updated','assigned','uploaded']]) @endif
                @if($id==='access') @include('cases._events',['actions'=>['viewed','opened_edit','viewed_history','downloaded']]) @endif
                @if($id==='assignments')<p class="text-muted mt-3">Cambiar el responsable transfiere el acceso al expediente. Los datos de una familia vinculada mantienen sus propios permisos.</p>@include('cases._events',['actions'=>['assigned']])@endif
                @if($id==='summary')<p class="text-muted mt-4">La localización se documenta en este expediente; no hay búsqueda de concordancias ni conexión con Primero habilitada.</p>@endif
                @if(in_array($id,['media','documents']))
                    @foreach(($id==='media'?['photo'=>'Fotos','audio'=>'Audio grabado']:['document'=>'Otros documentos']) as $category=>$categoryLabel)
                        <h3 class="h5 mt-4">{{ $categoryLabel }}</h3>
                        @forelse($caseRecord->attachments->where('category',$category) as $attachment)<a class="case-related" href="{{ route('cases.attachments.download',[$caseRecord,$attachment]) }}"><i class="ri-download-line"></i> {{ $attachment->original_name }} <small>{{ number_format($attachment->size/1024,1) }} KB</small></a>@empty<p class="text-muted">No hay archivos registrados.</p>@endforelse
                    @endforeach
                    @if($caseRecord->exists) @can('update',$caseRecord)<p class="mt-3">Para adjuntar archivos, use «Adjuntar archivo privado» al final de esta página.</p>@endcan @else<p class="alert alert-info">Guarde el caso para habilitar la carga de archivos privados.</p>@endif
                @endif
                <div class="case-panel-navigation">@if(!$loop->first)<button type="button" class="btn btn-outline-secondary" data-step="-1">Anterior</button>@else<span></span>@endif @if(!$loop->last)<button type="button" class="btn btn-outline-primary" data-step="1">Siguiente</button>@elseif(!$readOnly)<button type="submit" class="btn btn-primary">Guardar expediente</button>@endif</div>
            </section>
            @endforeach
        </div>
    </div>
    @if(!$readOnly)<input type="hidden" name="form_complete" value="1">@endif
</form>
@if($caseRecord->exists) @can('update',$caseRecord)
<details class="case-surface p-4 mt-4"><summary>Adjuntar archivo privado</summary><p class="text-muted mt-3">Fotos: PNG, JPEG, GIF. Audio: MP3, M4A. Documentos: PDF, TXT, DOC, DOCX, XLS, XLSX, CSV, JPG, PNG. Máximo 15 MB por archivo. Guarde primero los cambios del expediente.</p>
    <form method="post" action="{{ route('cases.attachments.store',$caseRecord) }}" enctype="multipart/form-data" class="row g-3">@csrf
        <div class="col-md-3"><label for="attachment-category" class="form-label">Tipo</label><select name="category" id="attachment-category" class="form-select"><option value="photo">Foto</option><option value="audio">Audio</option><option value="document">Documento</option></select></div>
        <div class="col-md-6"><label for="attachment-file" class="form-label">Archivo</label><input type="file" name="file" id="attachment-file" class="form-control" required></div><div class="col-md-3 align-self-end"><button type="submit" class="btn btn-primary">Subir archivo</button></div>
    </form>
</details>@endcan @endif
</div>
@endsection
