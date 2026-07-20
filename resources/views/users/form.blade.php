<div class="form-grid two-cols">
    <label>Nombre completo *
        <input type="text" name="name" value="{{ old('name', $managedUser?->name) }}" autocomplete="name" required autofocus>
    </label>
    <label>Correo electrónico *
        <input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" autocomplete="email" required>
    </label>
    <label>Rol *
        <select name="role" required>
            @foreach ($roleLabels as $role => $label)
                <option value="{{ $role }}" @selected(old('role', $managedUser?->role ?? 'reporter') === $role)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>{{ $managedUser ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}
        <input type="password" name="password" autocomplete="new-password" @required(! $managedUser)>
    </label>
    <label class="span-two">{{ $managedUser ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *' }}
        <input type="password" name="password_confirmation" autocomplete="new-password" @required(! $managedUser)>
    </label>
</div>
<div class="form-actions">
    <a class="button button-secondary" href="{{ route('users.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
