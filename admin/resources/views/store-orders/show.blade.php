@extends('layouts.admin')

@php
    $store = $order->store;
    $bs = fn ($amount) => 'Bs '.number_format((float) $amount, 2);
@endphp

@section('title', 'Venta '.$order->code)
@section('page_title', 'Tiendas')
@section('page_subtitle', 'Venta '.$order->code)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('store-orders.index') }}">Ventas</a></li>
    <li class="breadcrumb-item active">{{ $order->code }}</li>
@endsection

@section('content')
    @if ($order->canBeRefunded())
        <div class="alert alert-warning">
            <i class="fas fa-hand-holding-usd mr-1"></i>
            Esta venta fue pagada y luego anulada: hay que devolver <strong>{{ $bs($order->total) }}</strong> al cliente.
        </div>
    @elseif ($order->canBeDelivered() && $order->source === 'app')
        <div class="alert alert-info">
            <i class="fas fa-box-open mr-1"></i>
            Pedido pagado desde la app: el cliente lo retirará en la tienda mostrando el código <strong>{{ $order->code }}</strong>.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-receipt mr-1"></i> {{ $order->code }}
                        <span class="ml-2">@include('store-orders.partials.status')</span>
                    </h3>
                    <div class="card-tools text-muted small">
                        {{ $store->name }} · {{ $store->court->name }}
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-right">Precio unit.</th>
                                <th class="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->name }}
                                        @if ($item->product === null)<small class="text-muted">(producto eliminado)</small>@endif
                                    </td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-right">
                                        @if ($item->discount_percent > 0)
                                            <del class="text-muted small">{{ $bs($item->list_price) }}</del>
                                            <span class="badge badge-danger">-{{ $item->discount_percent }}%</span><br>
                                        @endif
                                        {{ $bs($item->unit_price) }}
                                    </td>
                                    <td class="text-right">{{ $bs($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if ((float) $order->discount_amount > 0)
                                <tr class="text-muted">
                                    <td colspan="3" class="text-right">Subtotal</td><td class="text-right">{{ $bs($order->subtotal) }}</td>
                                </tr>
                                <tr class="text-muted">
                                    <td colspan="3" class="text-right">Descuentos</td><td class="text-right">− {{ $bs($order->discount_amount) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th colspan="3" class="text-right">Total</th><th class="text-right h5 mb-0">{{ $bs($order->total) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($order->notes || $order->status === \App\Models\StoreOrder::STATUS_CANCELLED)
                    <div class="card-body border-top">
                        <dl class="row mb-0">
                            @if ($order->notes)
                                <dt class="col-sm-4">Notas</dt><dd class="col-sm-8">{!! nl2br(e($order->notes)) !!}</dd>
                            @endif
                            @if ($order->status === \App\Models\StoreOrder::STATUS_CANCELLED)
                                <dt class="col-sm-4 text-danger">Motivo de anulación</dt><dd class="col-sm-8">{{ $order->cancellation_reason ?: '—' }}</dd>
                            @endif
                        </dl>
                    </div>
                @endif
                @canany(['store_orders.cancel', 'store_orders.payments'])
                    @if ($order->canBeCancelled() || $order->canBeDelivered() || $order->canBeRefunded())
                        <div class="card-footer text-right">
                            @can('store_orders.payments')
                                @if ($order->canBeDelivered())
                                    <form method="POST" action="{{ route('store-orders.deliver', $order) }}" class="d-inline"
                                          data-confirm="¿Confirmas que entregaste los productos de {{ $order->code }} al cliente?">
                                        @csrf
                                        <button type="submit" class="btn btn-success"><i class="fas fa-box-open"></i> Marcar como entregada</button>
                                    </form>
                                @endif
                                @if ($order->canBeRefunded())
                                    <form method="POST" action="{{ route('store-orders.refund', $order) }}" class="d-inline"
                                          data-confirm="¿Confirmas que devolviste {{ $bs($order->total) }} al cliente?">
                                        @csrf
                                        <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> Marcar como reembolsada</button>
                                    </form>
                                @endif
                            @endcan
                            @can('store_orders.cancel')
                                @if ($order->canBeCancelled())
                                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#cancel-modal">
                                        <i class="fas fa-ban"></i> Anular venta
                                    </button>
                                @endif
                            @endcan
                        </div>
                    @endif
                @endcanany
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title">Cliente</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ $order->customerName() }}</dd>
                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8">
                            @if ($phone = $order->customerPhone())
                                {{ $phone }}
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', str_starts_with($phone, '+') ? $phone : '591'.$phone) }}" target="_blank" rel="noopener" class="ml-1 text-success" title="Escribir por WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $order->user?->email ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title">Pago y entrega</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Origen</dt><dd class="col-sm-8">{{ $order->source === 'admin' ? 'Mostrador' : 'App (retiro en tienda)' }}</dd>
                        <dt class="col-sm-4">Método</dt><dd class="col-sm-8">{{ $paymentMethods[$order->payment_method] ?? '—' }}</dd>
                        <dt class="col-sm-4">Pagado</dt><dd class="col-sm-8">{{ $order->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        <dt class="col-sm-4">Entregado</dt><dd class="col-sm-8">{{ $order->delivered_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        @if ($order->refunded_at)
                            <dt class="col-sm-4">Reembolsado</dt><dd class="col-sm-8">{{ $order->refunded_at->format('d/m/Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card card-secondary card-outline">
                <div class="card-header"><h3 class="card-title">Historial</h3></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-plus-circle text-primary mr-1"></i>
                            <strong>{{ $order->created_at->format('d/m/Y H:i') }}</strong> · Creada
                            {{ $order->createdBy ? 'por '.$order->createdBy->name.' (mostrador)' : 'desde la app' }}
                        </li>
                        @if ($order->paid_at)
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success mr-1"></i>
                                <strong>{{ $order->paid_at->format('d/m/Y H:i') }}</strong> · Pagada ({{ $paymentMethods[$order->payment_method] ?? '—' }}),
                                {{ $order->itemsCount() }} {{ $order->itemsCount() === 1 ? 'unidad salió' : 'unidades salieron' }} del stock
                            </li>
                        @endif
                        @if ($order->isExpired())
                            <li class="mb-2">
                                <i class="fas fa-hourglass-end text-secondary mr-1"></i>
                                <strong>{{ $order->created_at->copy()->addMinutes(\App\Models\StoreOrder::PAYMENT_WINDOW_MINUTES)->format('d/m/Y H:i') }}</strong> · Expiró sin pago, las unidades se liberaron
                            </li>
                        @endif
                        @if ($order->delivered_at)
                            <li class="mb-2">
                                <i class="fas fa-box-open text-success mr-1"></i>
                                <strong>{{ $order->delivered_at->format('d/m/Y H:i') }}</strong> · Entregada{{ $order->deliveredBy ? ' por '.$order->deliveredBy->name : '' }}
                            </li>
                        @endif
                        @if ($order->cancelled_at)
                            <li class="mb-2">
                                <i class="fas fa-ban text-danger mr-1"></i>
                                <strong>{{ $order->cancelled_at->format('d/m/Y H:i') }}</strong> · Anulada
                                {{ $order->cancelled_by === $order->user_id ? 'por el cliente' : 'por '.($order->cancelledBy?->name ?? 'la tienda') }}
                                @if ($order->stockMovements->contains('type', \App\Models\StockMovement::TYPE_RETURN))
                                    · las unidades volvieron al stock
                                @endif
                            </li>
                        @endif
                        @if ($order->refunded_at)
                            <li class="mb-2">
                                <i class="fas fa-undo text-warning mr-1"></i>
                                <strong>{{ $order->refunded_at->format('d/m/Y H:i') }}</strong> · Dinero devuelto al cliente
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @can('store_orders.cancel')
        @if ($order->canBeCancelled())
            <div class="modal fade" id="cancel-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="POST" action="{{ route('store-orders.cancel', $order) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Anular venta {{ $order->code }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            @include('partials.errors', ['bag' => 'cancel'])
                            @if ($order->paid_at)
                                <p class="text-danger font-weight-bold">La venta ya está pagada: deberás devolver {{ $bs($order->total) }} al cliente.</p>
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" id="restock" name="restock" value="1" @checked(old('restock', true))>
                                    <label class="custom-control-label" for="restock">Devolver las {{ $order->itemsCount() }} unidades al stock</label>
                                    <small class="form-text text-muted">Desmárcalo si el cliente se quedó con los productos o no se pueden volver a vender.</small>
                                </div>
                            @else
                                <p>Las unidades apartadas por este pedido quedarán libres para otros clientes.</p>
                            @endif
                            <div class="form-group mb-0">
                                <label for="cancellation_reason">Motivo *</label>
                                <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required minlength="5" maxlength="500"
                                          class="form-control @error('cancellation_reason', 'cancel') is-invalid @enderror"
                                          placeholder="Ej.: producto sin stock real, solicitud del cliente...">{{ old('cancellation_reason') }}</textarea>
                                @if ($order->source === 'app')
                                    <small class="text-muted">El cliente verá este motivo en la app.</small>
                                @endif
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Volver</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Anular venta</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection

@push('scripts')
    <script>
        @if ($errors->cancel->any()) $('#cancel-modal').modal('show'); @endif
    </script>
@endpush
