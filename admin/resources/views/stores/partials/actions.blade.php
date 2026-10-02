<div class="text-right action-buttons">
    <a href="{{ route('stores.show', $store) }}" class="btn btn-link text-info" title="Productos y stock"><i class="fas fa-boxes"></i></a>
    @can('store_orders.store')
        <a href="{{ route('store-orders.create', ['store_id' => $store->id]) }}" class="btn btn-link text-success" title="Registrar venta"><i class="fas fa-cash-register"></i></a>
    @endcan
    @can('stores.update')
        <a href="{{ route('stores.edit', $store) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('stores.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('stores.destroy', $store) }}"
                data-name="la tienda {{ $store->name }}"
                data-warning="Se eliminarán sus {{ $store->products_count }} producto(s) y su historial de stock."
                data-table="stores-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
