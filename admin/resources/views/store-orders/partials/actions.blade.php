<div class="action-buttons">
    @can('store_orders.show')
        <a href="{{ route('store-orders.show', $order) }}" class="btn btn-link text-primary" title="Ver detalle"><i class="fas fa-eye"></i></a>
    @endcan
</div>
