<div class="d-flex align-items-center">
    @if ($photo = $product->photos->first())
        <img src="{{ $photo->src }}" alt="" class="rounded mr-2" style="width: 44px; height: 44px; object-fit: cover;" loading="lazy">
    @else
        <span class="rounded mr-2 bg-light d-inline-flex align-items-center justify-content-center text-muted" style="width: 44px; height: 44px;"><i class="fas fa-box"></i></span>
    @endif
    <div>
        <strong>{{ $product->name }}</strong>
        @if ($product->sku)<br><small class="text-muted">SKU {{ $product->sku }}</small>@endif
    </div>
</div>
