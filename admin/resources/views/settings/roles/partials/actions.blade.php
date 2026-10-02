<div class="text-right action-buttons">
    @if (! $protected)
        @can('roles.update')
            <a href="{{ route('settings.roles.edit', $role) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
        @endcan
        @can('roles.destroy')
            <button type="button" class="btn btn-link text-danger" title="Eliminar"
                    data-delete-url="{{ route('settings.roles.destroy', $role) }}"
                    data-name="el rol {{ $role->name }}"
                    data-warning="{{ $role->users_count }} usuario(s) perderán este rol."
                    data-table="roles-table"><i class="fas fa-trash-alt"></i></button>
        @endcan
    @else
        <span class="text-muted small"><i class="fas fa-lock"></i> protegido</span>
    @endif
</div>
