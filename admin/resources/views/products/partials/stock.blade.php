@if ($product->stock === 0)
    <span class="badge badge-danger">Agotado</span>
@else
    <strong class="{{ $product->isLowStock() ? 'text-warning' : '' }}">{{ $product->stock }}</strong>
    @if ($product->isLowStock())
        <i class="fas fa-exclamation-triangle text-warning" title="Stock bajo (mínimo {{ $product->min_stock }})"></i>
    @endif
@endif
@if ($held > 0)
    <br><small class="text-muted" title="Apartadas por pedidos de la app pendientes de pago">{{ $held }} apartadas</small>
@endif
