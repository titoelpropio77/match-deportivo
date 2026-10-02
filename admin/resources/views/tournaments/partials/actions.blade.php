<div class="action-buttons">
    <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-link text-primary" title="Ver"><i class="fas fa-eye"></i></a>
    @can('tournaments.update')
        <a href="{{ route('tournaments.edit', $tournament) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('tournaments.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('tournaments.destroy', $tournament) }}"
                data-name="el torneo {{ $tournament->name }}"
                data-warning="Se eliminarán también sus inscripciones y su fixture."
                data-table="tournaments-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
