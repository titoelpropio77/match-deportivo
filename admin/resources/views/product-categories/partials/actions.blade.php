<div class="text-right action-buttons">
    @can('product_categories.update')
        <a href="{{ route('product-categories.edit', $category) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('product_categories.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('product-categories.destroy', $category) }}"
                data-name="la categoría {{ $category->name }}"
                data-warning="Se quitará de {{ $category->stores_count }} tienda(s)."
                data-table="product-categories-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
