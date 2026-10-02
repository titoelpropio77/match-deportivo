@extends('layouts.admin')

@section('title', 'Registrar venta')
@section('page_title', 'Tiendas')
@section('page_subtitle', 'Registrar venta de mostrador')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('store-orders.index') }}">Ventas</a></li>
    <li class="breadcrumb-item active">Registrar</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('store-orders.partials.tabs')</div>
        <form method="POST" action="{{ route('store-orders.store') }}" id="sale-form">
            @csrf
            <div class="card-body">
                @include('partials.errors')
                @if ($stores->isEmpty())
                    <div class="alert alert-info mb-0">
                        Tus centros todavía no tienen tiendas.
                        @can('stores.store') <a href="{{ route('stores.create') }}">Crea una tienda</a> para empezar a vender. @endcan
                    </div>
                @else
                    <p class="text-muted">Venta cobrada en el mostrador: las unidades salen del stock al registrarla.</p>
                    <div class="row">
                        <div class="col-md-5 form-group">
                            <label for="store_id">Tienda *</label>
                            <select id="store_id" name="store_id" class="form-control @error('store_id') is-invalid @enderror" required>
                                <option value="">— Selecciona —</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected((string) $selectedStore === (string) $store->id)>{{ $store->name }} · {{ $store->court->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h5 class="mb-2">Productos</h5>
                    <div class="table-responsive">
                        <table class="table table-sm" id="sale-lines">
                            <thead>
                                <tr>
                                    <th style="min-width: 260px;">Producto</th>
                                    <th style="width: 110px;">Cantidad</th>
                                    <th class="text-right" style="width: 140px;">Precio unit.</th>
                                    <th class="text-right" style="width: 120px;">Importe</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="fas fa-plus"></i> Agregar producto</button>
                                        <span class="text-muted ml-2" id="no-products" style="display: none;">Esta tienda no tiene productos a la venta.</span>
                                    </td>
                                </tr>
                                <tr class="text-muted" id="discount-row" style="display: none;">
                                    <td colspan="3" class="text-right">Descuentos</td>
                                    <td class="text-right" id="sale-discount"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <th colspan="3" class="text-right">Total</th>
                                    <th class="text-right h5 mb-0" id="sale-total">Bs 0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <h5 class="mb-3 mt-2">Cliente <small class="text-muted">(opcional)</small></h5>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="customer_name">Nombre</label>
                            <input id="customer_name" name="customer_name" maxlength="255" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}" placeholder="Cliente de mostrador">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="customer_phone">Teléfono</label>
                            <input id="customer_phone" name="customer_phone" maxlength="30" class="form-control @error('customer_phone') is-invalid @enderror" value="{{ old('customer_phone') }}">
                        </div>
                        <div class="col-md-5 form-group">
                            <label for="user_email">Email de su cuenta en la app</label>
                            <input type="email" id="user_email" name="user_email" class="form-control @error('user_email') is-invalid @enderror" value="{{ old('user_email') }}">
                            <small class="text-muted">Si lo indicas, la compra aparecerá en "Mis compras" de su app.</small>
                        </div>
                    </div>

                    <h5 class="mb-3 mt-2">Pago</h5>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label for="payment_method">Método de pago *</label>
                            <select id="payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <div class="custom-control custom-checkbox mt-md-4 pt-md-2">
                                <input type="checkbox" class="custom-control-input" id="delivered" name="delivered" value="1" @checked(old('delivered', true))>
                                <label class="custom-control-label" for="delivered">Entregado en el momento</label>
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="notes">Notas</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                @endif
            </div>
            @if ($stores->isNotEmpty())
                <div class="card-footer text-right">
                    <a href="{{ route('store-orders.index') }}" class="btn btn-default">Cancelar</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-cash-register"></i> Registrar venta</button>
                </div>
            @endif
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const catalog = @json($catalog);
            const oldItems = @json(array_values(old('items', [])));
            const errors = @json($errors->getMessages());
            const $store = $('#store_id');
            const $body = $('#sale-lines tbody');
            const money = function (amount) { return 'Bs ' + amount.toFixed(2); };
            let counter = 0;

            function products() {
                return catalog[$store.val()] || [];
            }

            function find(id) {
                return products().find(function (product) { return String(product.id) === String(id); });
            }

            function addLine(item) {
                const index = counter++;
                const $select = $('<select class="form-control form-control-sm" required>').attr('name', 'items[' + index + '][product_id]');
                $select.append('<option value="">— Selecciona —</option>');
                products().forEach(function (product) {
                    const label = product.name + (product.sku ? ' (' + product.sku + ')' : '') + ' — ' + money(product.price)
                        + (product.available > 0 ? ' · ' + product.available + ' disp.' : ' · agotado');
                    $select.append($('<option>').val(product.id).text(label).prop('disabled', product.available === 0));
                });
                const $quantity = $('<input type="number" min="1" class="form-control form-control-sm" required>')
                    .attr('name', 'items[' + index + '][quantity]').val(item ? item.quantity : 1);
                const $row = $('<tr>').append(
                    $('<td>').append($select),
                    $('<td>').append($quantity),
                    $('<td class="text-right align-middle" data-unit>'),
                    $('<td class="text-right align-middle font-weight-bold" data-amount>'),
                    $('<td class="align-middle">').append('<button type="button" class="btn btn-xs btn-link text-danger" data-remove title="Quitar"><i class="fas fa-times"></i></button>')
                );
                if (item && item.product_id) $select.val(String(item.product_id));
                ['product_id', 'quantity'].forEach(function (field) {
                    const message = errors['items.' + (item ? item.index : -1) + '.' + field];
                    if (message) {
                        (field === 'product_id' ? $select : $quantity).addClass('is-invalid');
                    }
                });
                $body.append($row);
                refresh();
            }

            function refresh() {
                let total = 0;
                let list = 0;
                $body.find('tr').each(function () {
                    const $row = $(this);
                    const product = find($row.find('select').val());
                    const $quantity = $row.find('input');
                    if (!product) {
                        $row.find('[data-unit], [data-amount]').text('');
                        $quantity.removeAttr('max');
                        return;
                    }
                    const quantity = parseInt($quantity.val(), 10) || 0;
                    $quantity.attr('max', product.available);
                    $row.find('[data-unit]').html(product.discount_percent > 0
                        ? '<del class="text-muted small">' + money(product.list_price) + '</del> ' + money(product.price)
                        : money(product.price));
                    $row.find('[data-amount]').text(money(product.price * quantity));
                    total += product.price * quantity;
                    list += product.list_price * quantity;
                });
                $('#sale-total').text(money(total));
                $('#sale-discount').text('− ' + money(list - total));
                $('#discount-row').toggle(list - total > 0.001);
            }

            function reset() {
                $body.empty();
                const has = products().length > 0;
                $('#add-line').toggle(has);
                $('#no-products').toggle(!!$store.val() && !has);
                if (has) addLine();
            }

            $store.on('change', reset);
            $('#add-line').on('click', function () { addLine(); });
            $body.on('change input', 'select, input', refresh);
            $body.on('click', '[data-remove]', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            if (oldItems.length && $store.val()) {
                $('#add-line').show();
                oldItems.forEach(function (item, index) { addLine(Object.assign({ index: index }, item)); });
            } else {
                reset();
            }
        })();
    </script>
@endpush
