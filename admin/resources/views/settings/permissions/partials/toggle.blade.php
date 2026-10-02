@php
    $isSuper = $role->name === 'superadmin';
    $checked = $isSuper || $permission->roles->contains('id', $role->id);
@endphp
<div class="custom-control custom-checkbox">
    <input type="checkbox" class="custom-control-input permission-toggle"
           id="perm_{{ $permission->id }}_{{ $role->id }}"
           data-url="{{ route('settings.permissions.toggle', [$permission, $role]) }}"
           @checked($checked)
           @disabled($isSuper || ! $canAssign)
           @if ($isSuper) title="superadmin tiene todos los permisos" @endif>
    <label class="custom-control-label" for="perm_{{ $permission->id }}_{{ $role->id }}">{{-- Text only for export/print. --}}<span class="d-none">{{ $checked ? 'Sí' : '' }}</span></label>
</div>
