@extends('layouts.app')

@section('title', 'Mi perfil | Respuesta ASONACOP')

@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Cuenta de usuario</p>
        <h1>Mi perfil</h1>
        <p class="muted">Consulte su información y actualice sus datos de acceso.</p>
    </div>
</section>

<div class="profile-layout">
    <section class="content-card profile-summary-card">
        <div class="text-center mb-4">
            <img src="{{ $user->profile_photo_url }}" alt="Foto de {{ $user->name }}" class="rounded-circle avatar-xl object-fit-cover">
            <h2 class="mt-3 mb-0">{{ $user->name }}</h2>
        </div>
        <h2>Información de la cuenta</h2>
        <dl class="detail-list">
            <div><dt>Correo electrónico</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Rol</dt><dd>{{ \App\Models\User::roleLabels()[$user->role] ?? $user->role }}</dd></div>
            <div><dt>Estado de la cuenta</dt><dd><span class="status {{ $user->is_active ? 'status-active' : 'status-inactive' }}">{{ $user->is_active ? 'Activa' : 'Inactiva' }}</span></dd></div>
            <div><dt>Proyectos asignados</dt><dd>{{ $user->projects->map(fn ($project) => $project->codigo.' — '.$project->descripcion)->join("\n") ?: 'Sin proyectos asignados' }}</dd></div>
            <div><dt>Estados asignados</dt><dd>{{ $user->isAdministrator() || $user->countrywide_access ? 'Todo el país' : ($user->assignedStates->pluck('name')->join(', ') ?: 'Sin estados completos') }}</dd></div>
            @unless($user->isAdministrator() || $user->countrywide_access)
                <div><dt>Municipios específicos</dt><dd>{{ $user->assignedMunicipalities->map(fn ($municipality) => $municipality->state->name.' / '.$municipality->name)->join(', ') ?: 'Sin municipios específicos' }}</dd></div>
            @endunless
        </dl>
    </section>

    <section class="content-card profile-edit-card">
        <h2>Actualizar mis datos</h2>
        <form method="post" action="{{ route('profile.update') }}" class="stack-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <label>Nombre completo *
                <input type="text" name="name" value="{{ old('name', $user->name) }}" minlength="3" maxlength="120" required autofocus>
            </label>
            <label>Foto de perfil
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                <small class="muted">Formatos JPG, PNG o WebP. Tamaño máximo: 5 MB.</small>
            </label>
            <hr>
            <div>
                <h3>Cambiar contraseña</h3>
                <p class="muted">Deje estos campos vacíos si no desea cambiarla.</p>
            </div>
            <label>Contraseña actual
                <input type="password" name="current_password" autocomplete="current-password">
            </label>
            <label>Nueva contraseña
                <input type="password" name="password" minlength="8" autocomplete="new-password">
            </label>
            <label>Confirmar nueva contraseña
                <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
            </label>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('dashboard') }}">Cancelar</a>
                <button class="button button-primary" type="submit">Guardar cambios</button>
            </div>
        </form>
    </section>
</div>
@endsection
