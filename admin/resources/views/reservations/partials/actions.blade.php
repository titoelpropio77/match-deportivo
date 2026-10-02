<div class="action-buttons">
    @can('reservations.show')
        <a href="{{ route('reservations.show', $reservation) }}" class="btn btn-link text-primary" title="Ver detalle"><i class="fas fa-eye"></i></a>
    @endcan
</div>
