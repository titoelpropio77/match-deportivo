<div class="text-right action-buttons">
    @can('permissions.update')
        <a href="{{ route('settings.permissions.edit', $permission) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('permissions.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('settings.permissions.destroy', $permission) }}"
                data-name="el permiso {{ $permission->name }}"
                data-warning="Las pantallas protegidas por este permiso quedarán accesibles solo para superadmin."
                data-table="permissions-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
