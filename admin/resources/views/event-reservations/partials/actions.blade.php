<div class="action-buttons">
    @can('event_reservations.show')
        <a href="{{ route('event-reservations.show', $reservation) }}" class="btn btn-link text-primary" title="Ver detalle"><i class="fas fa-eye"></i></a>
    @endcan
</div>
