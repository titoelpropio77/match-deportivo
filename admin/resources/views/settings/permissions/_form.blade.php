@include('partials.errors')
@csrf
<div class="form-group">
    <label for="name">Nombre del permiso *</label>
    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $permission->name) }}" placeholder="modulo.accion" required>
    <small class="text-muted">Formato <code>modulo.accion</code>, ej: <code>courts.update</code>. El módulo agrupa los permisos en la lista.</small>
</div>
<label>Asignar a roles</label>
@php($selectedRoles = old('roles', $permission->exists ? $permission->roles->pluck('name')->all() : []))
<div class="row">
    @foreach ($roles as $role)
        <div class="col-md-3 col-6">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="role_{{ $role->id }}" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles, true))>
                <label class="custom-control-label font-weight-normal" for="role_{{ $role->id }}">{{ $role->name }}</label>
            </div>
        </div>
    @endforeach
</div>
<p class="text-muted small mt-2"><i class="fas fa-info-circle"></i> superadmin siempre tiene todos los permisos.</p>
