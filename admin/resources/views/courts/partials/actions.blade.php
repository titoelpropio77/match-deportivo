<div class="action-buttons">
    @can('courts.show')
        <a href="{{ route('courts.show', $court) }}" class="btn btn-link text-primary" title="Ver"><i class="fas fa-eye"></i></a>
    @endcan
    @can('courts.update')
        <a href="{{ route('courts.edit', $court) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('courts.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('courts.destroy', $court) }}"
                data-name="{{ $court->name }}"
                data-warning="Se eliminarán también sus canchas físicas, fotos y reservas."
                data-table="courts-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
