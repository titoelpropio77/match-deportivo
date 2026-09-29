<div class="action-buttons">
    @can('users.show')
        <a href="{{ route('users.show', $user) }}" class="btn btn-link text-primary" title="Ver"><i class="fas fa-eye"></i></a>
    @endcan
    @can('users.update')
        <a href="{{ route('users.edit', $user) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('users.destroy')
        @unless ($user->is(auth()->user()))
            <button type="button" class="btn btn-link text-danger" title="Eliminar"
                    data-delete-url="{{ route('users.destroy', $user) }}"
                    data-name="a {{ $user->name }}"
                    data-warning="Se eliminarán también los partidos que organizó y sus inscripciones."
                    data-table="users-table"><i class="fas fa-trash-alt"></i></button>
        @endunless
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-link text-primary dropdown-toggle" data-toggle="dropdown" title="Más"><i class="fas fa-cog"></i></button>
        <div class="dropdown-menu dropdown-menu-right">
            @can('users.update')
                <a class="dropdown-item" href="{{ route('users.edit', $user) }}#roles"><i class="fas fa-user-shield mr-2"></i> Asignar roles</a>
                <a class="dropdown-item" href="{{ route('users.edit', $user) }}#password"><i class="fas fa-key mr-2"></i> Cambiar contraseña</a>
            @endcan
            <a class="dropdown-item" href="mailto:{{ $user->email }}"><i class="fas fa-envelope mr-2"></i> Enviar email</a>
        </div>
    </div>
</div>
