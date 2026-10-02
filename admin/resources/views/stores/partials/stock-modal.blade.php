{{--
    One modal for every product row: the "Stock" button of products.partials.actions fills it
    with the product (data-*). After a validation error it reopens for the same product.
--}}
<div class="modal fade" id="stock-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="" class="modal-content" id="stock-form" data-action-template="{{ route('stores.products.stock', [$store, '__PRODUCT__']) }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ old('product_id') }}">
            <input type="hidden" name="product_name" value="{{ old('product_name') }}">
            <input type="hidden" name="product_stock" value="{{ old('product_stock') }}">
            <input type="hidden" name="product_held" value="{{ old('product_held') }}">
            <div class="modal-header">
                <h5 class="modal-title">Movimiento de stock · <span data-stock-name></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                @include('partials.errors', ['bag' => 'stock'])
                <p class="mb-3">
                    Stock actual: <strong data-stock-current></strong> unidades
                    <span data-stock-held-wrap class="text-muted">· <span data-stock-held></span> apartadas por pedidos de la app sin pagar</span>
                </p>
                <div class="form-group">
                    <label>Tipo de movimiento *</label>
                    @foreach ($movementTypes as $value => [$label])
                        <div class="custom-control custom-radio">
                            <input type="radio" id="stock-type-{{ $value }}" name="type" value="{{ $value }}" class="custom-control-input" @checked(old('type', 'restock') === $value)>
                            <label class="custom-control-label font-weight-normal" for="stock-type-{{ $value }}">
                                {{ $label }}
                                <small class="text-muted">
                                    @switch($value)
                                        @case('restock') — llegó mercadería, suma unidades. @break
                                        @case('loss') — producto dañado, vencido o de uso interno, resta unidades. @break
                                        @case('adjustment') — contaste el estante: el stock pasa a ser la cantidad indicada. @break
                                    @endswitch
                                </small>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="form-row">
                    <div class="col-sm-5 form-group">
                        <label for="stock-quantity" data-stock-quantity-label>Cantidad *</label>
                        <input type="number" id="stock-quantity" name="quantity" min="0" required class="form-control @error('quantity', 'stock') is-invalid @enderror" value="{{ old('quantity') }}">
                        <small class="text-muted" data-stock-preview></small>
                    </div>
                    <div class="col-sm-7 form-group">
                        <label for="stock-reason">Motivo</label>
                        <input id="stock-reason" name="reason" maxlength="255" class="form-control @error('reason', 'stock') is-invalid @enderror" value="{{ old('reason') }}" placeholder="Ej: compra a proveedor, pelota pinchada...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Registrar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const $modal = $('#stock-modal');
            const $form = $('#stock-form');

            function preview() {
                const stock = parseInt($form.find('[name=product_stock]').val(), 10) || 0;
                const quantity = parseInt($('#stock-quantity').val(), 10);
                const type = $form.find('[name=type]:checked').val();
                $('[data-stock-quantity-label]').text(type === 'adjustment' ? 'Cantidad contada *' : 'Cantidad *');
                if (isNaN(quantity)) { $('[data-stock-preview]').text(''); return; }
                const next = type === 'restock' ? stock + quantity : (type === 'loss' ? stock - quantity : quantity);
                $('[data-stock-preview]').text('Quedarán ' + next + ' unidades.');
            }

            function open(product) {
                $form.attr('action', $form.data('action-template').replace('__PRODUCT__', product.id));
                $form.find('[name=product_id]').val(product.id);
                $form.find('[name=product_name]').val(product.name);
                $form.find('[name=product_stock]').val(product.stock);
                $form.find('[name=product_held]').val(product.held);
                $modal.find('[data-stock-name]').text(product.name);
                $modal.find('[data-stock-current]').text(product.stock);
                $modal.find('[data-stock-held]').text(product.held);
                $modal.find('[data-stock-held-wrap]').toggle(parseInt(product.held, 10) > 0);
                preview();
                $modal.modal('show');
            }

            $(document).on('click', '[data-stock-product]', function () {
                $form.find('.alert-danger').remove();
                $form.find('.is-invalid').removeClass('is-invalid');
                $('#stock-quantity, #stock-reason').val('');
                $('#stock-type-restock').prop('checked', true);
                open($(this).data('stock-product'));
            });
            $form.on('input change', 'input', preview);

            @if ($errors->stock->any() && old('product_id'))
                open({ id: @json(old('product_id')), name: @json(old('product_name')), stock: @json(old('product_stock')), held: @json(old('product_held')) });
            @endif
        })();
    </script>
@endpush
