@include('partials.errors')
@csrf
<div class="row">
    <div class="col-md-6 form-group">
        <label for="name">Nombre *</label>
        <input id="name" name="name" maxlength="150" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}" placeholder="Ej: Pelota de wally profesional">
    </div>
    <div class="col-md-3 form-group">
        <label for="product_category_id">Categoría *</label>
        <select id="product_category_id" name="product_category_id" required class="form-control @error('product_category_id') is-invalid @enderror">
            <option value="">— Selecciona —</option>
            @foreach ($store->categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('product_category_id', $product->product_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Las que vende la tienda (<a href="{{ route('stores.edit', $store) }}">cambiar</a>).</small>
    </div>
    <div class="col-md-3 form-group">
        <label for="sku">Código (SKU)</label>
        <input id="sku" name="sku" maxlength="60" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku', $product->sku) }}">
    </div>
    <div class="col-md-3 form-group">
        <label for="price">Precio de venta (Bs) *</label>
        <input type="number" id="price" name="price" min="0" step="0.01" required class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $product->price) }}">
    </div>
    <div class="col-md-3 form-group">
        <label for="discount_percent">Descuento (%)</label>
        <input type="number" id="discount_percent" name="discount_percent" min="0" max="{{ \App\Http\Requests\Stores\ProductRequest::MAX_DISCOUNT }}" class="form-control @error('discount_percent') is-invalid @enderror" value="{{ old('discount_percent', $product->discount_percent) }}">
        <small class="text-muted" id="final-price"></small>
    </div>
    <div class="col-md-3 form-group">
        <label for="stock">{{ $product->exists ? 'Stock actual' : 'Stock inicial *' }}</label>
        @if ($product->exists)
            <input class="form-control" value="{{ $product->stock }} unidades{{ $held > 0 ? " ($held apartadas)" : '' }}" disabled>
            <small class="text-muted">Se cambia con <i class="fas fa-dolly"></i> "Movimiento de stock" en la lista de productos.</small>
        @else
            <input type="number" id="stock" name="stock" min="0" required class="form-control @error('stock') is-invalid @enderror" value="{{ old('stock', 0) }}">
            <small class="text-muted">Unidades que hay hoy en la tienda.</small>
        @endif
    </div>
    <div class="col-md-3 form-group">
        <label for="min_stock">Avisar con stock bajo en</label>
        <input type="number" id="min_stock" name="min_stock" min="0" class="form-control @error('min_stock') is-invalid @enderror" value="{{ old('min_stock', $product->min_stock) }}" placeholder="Ej: 3">
        <small class="text-muted">Opcional: marca el producto cuando queden estas unidades o menos.</small>
    </div>
    <div class="col-md-9 form-group">
        <label for="description">Descripción</label>
        <textarea id="description" name="description" rows="3" maxlength="2000" class="form-control @error('description') is-invalid @enderror" placeholder="Material, tallas, medidas...">{{ old('description', $product->description) }}</textarea>
    </div>
    <div class="col-md-3 form-group">
        <div class="custom-control custom-switch mt-md-4">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
            <label class="custom-control-label" for="is_active">A la venta (visible en la app)</label>
        </div>
    </div>
</div>

@include('partials.photos-input', [
    'photos' => $product->exists ? $product->photos : collect(),
    'alt' => 'Foto de '.$product->name,
    'hint' => 'JPG, PNG o WEBP, hasta 5 MB cada una y '.\App\Models\Product::MAX_PHOTOS.' por producto. La primera es la principal en la app.',
])

@push('scripts')
    <script>
        (function () {
            function refresh() {
                const price = parseFloat($('#price').val());
                const discount = parseInt($('#discount_percent').val(), 10) || 0;
                $('#final-price').text(!isNaN(price) && discount > 0 ? 'Precio final: Bs ' + (price * (100 - discount) / 100).toFixed(2) : '');
            }
            $('#price, #discount_percent').on('input', refresh);
            refresh();
        })();
    </script>
@endpush
