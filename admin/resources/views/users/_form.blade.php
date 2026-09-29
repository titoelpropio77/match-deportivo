@include('partials.errors')
@csrf
<div class="row">
    <div class="col-md-6 form-group">
        <label for="name">Nombre completo *</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
    </div>
    <div class="col-md-6 form-group">
        <label for="nickname">Nickname</label>
        <input id="nickname" name="nickname" class="form-control @error('nickname') is-invalid @enderror" value="{{ old('nickname', $user->nickname) }}">
    </div>
    <div class="col-md-6 form-group">
        <label for="email">Email *</label>
        <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
    </div>
    <div class="col-md-6 form-group">
        <label for="phone">Teléfono</label>
        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
    </div>
    <div class="col-md-6 form-group">
        <label for="gender">Género</label>
        <select id="gender" name="gender" class="form-control @error('gender') is-invalid @enderror">
            <option value="">— Sin especificar —</option>
            @foreach (\App\Models\User::GENDERS as $value => $label)
                <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 form-group">
        <label for="preferred_position">Posición preferida</label>
        <input id="preferred_position" name="preferred_position" class="form-control" value="{{ old('preferred_position', $user->preferred_position) }}" placeholder="Ej: Rematador, Colocador">
    </div>
</div>

<h5 class="mt-3" id="password"><i class="fas fa-key text-muted"></i> Contraseña</h5>
@if ($user->exists)
    <p class="text-muted small">Déjala vacía para mantener la actual.</p>
@endif
<div class="row">
    <div class="col-md-6 form-group">
        <label for="password_input">Contraseña {{ $user->exists ? '' : '*' }}</label>
        <input id="password_input" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
    </div>
    <div class="col-md-6 form-group">
        <label for="password_confirmation">Confirmar contraseña</label>
        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
    </div>
</div>

<h5 class="mt-3" id="roles"><i class="fas fa-user-shield text-muted"></i> Roles</h5>
@php($selectedRoles = old('roles', $user->exists ? $user->getRoleNames()->all() : []))
<div class="row">
    @foreach ($roles as $role)
        <div class="col-md-3 col-6">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="role_{{ $role->id }}" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles, true))>
                <label class="custom-control-label" for="role_{{ $role->id }}">{{ $role->name }}</label>
            </div>
        </div>
    @endforeach
</div>
<p class="text-muted small mt-2">Solo los usuarios con el permiso <code>dashboard.index</code> pueden entrar a este panel.</p>
