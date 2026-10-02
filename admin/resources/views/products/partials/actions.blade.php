<div class="text-right action-buttons">
    @can('products.stock')
        <button type="button" class="btn btn-link text-success" title="Movimiento de stock"
                data-stock-product="{{ json_encode(['id' => $product->id, 'name' => $product->name, 'stock' => $product->stock, 'held' => $held]) }}"><i class="fas fa-dolly"></i></button>
    @endcan
    @can('products.update')
        <a href="{{ route('stores.products.edit', [$store, $product]) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('products.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('stores.products.destroy', [$store, $product]) }}"
                data-name="el producto {{ $product->name }}"
                data-warning="Las ventas registradas conservan su detalle; se pierde su historial de stock."
                data-table="products-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
