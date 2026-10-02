@if ($product->discount_percent > 0)
    <del class="text-muted small">Bs {{ number_format((float) $product->price, 2) }}</del>
    <span class="badge badge-danger">-{{ $product->discount_percent }}%</span><br>
    <strong>Bs {{ number_format($product->finalPrice(), 2) }}</strong>
@else
    <strong>Bs {{ number_format((float) $product->price, 2) }}</strong>
@endif
