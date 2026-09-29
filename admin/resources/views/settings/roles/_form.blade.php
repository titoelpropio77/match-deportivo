@include('partials.errors')
@csrf
<div class="form-group">
    <label for="name">Nombre del rol *</label>
    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $role->name) }}" placeholder="ej: admin_cancha" required>
</div>

<div class="d-flex align-items-center mb-2">
    <label class="mb-0">Permisos</label>
    <div class="ml-auto">
        <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('.permission-check').prop('checked', true); $('[data-check-group]').prop('checked', true)">Marcar todos</button>
        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="$('.permission-check').prop('checked', false); $('[data-check-group]').prop('checked', false)">Quitar todos</button>
    </div>
</div>
@php($selected = old('permissions', $granted))
<div class="row">
    @foreach ($permissionGroups as $group => $permissions)
        <div class="col-md-6 col-xl-4 mb-3">
            <div class="border rounded h-100">
                <div class="bg-light px-3 py-2 border-bottom">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="group_{{ $group }}" data-check-group="{{ $group }}"
                               @checked($permissions->every(fn ($permission) => in_array($permission->name, $selected, true)))>
                        <label class="custom-control-label font-weight-bold" for="group_{{ $group }}">{{ $group }}</label>
                    </div>
                </div>
                <div class="px-3 py-2">
                    @foreach ($permissions as $permission)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input permission-check" id="permission_{{ $permission->id }}"
                                   name="permissions[]" value="{{ $permission->name }}" data-group="{{ $group }}"
                                   @checked(in_array($permission->name, $selected, true))>
                            <label class="custom-control-label font-weight-normal" for="permission_{{ $permission->id }}">{{ $permission->name }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
<p class="text-muted small"><i class="fas fa-info-circle"></i> Para entrar al panel el rol necesita <code>dashboard.index</code>.</p>
