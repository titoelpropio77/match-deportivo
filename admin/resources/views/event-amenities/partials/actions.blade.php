<div class="text-right action-buttons">
    @can('event_amenities.update')
        <a href="{{ route('event-amenities.edit', $amenity) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('event_amenities.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('event-amenities.destroy', $amenity) }}"
                data-name="el servicio {{ $amenity->name }}"
                data-warning="Se quitará de {{ $amenity->spaces_count }} espacio(s) para eventos."
                data-table="event-amenities-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
