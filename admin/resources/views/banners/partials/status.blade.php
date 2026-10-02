@if ($banner->isLive())
    <span class="badge badge-success">Visible en la app</span>
@elseif (! $banner->is_active)
    <span class="badge badge-secondary">Inactivo</span>
@elseif ($banner->starts_at && $banner->starts_at->isFuture())
    <span class="badge badge-info">Programado</span>
@else
    <span class="badge badge-dark">Vencido</span>
@endif
@if ($banner->starts_at || $banner->ends_at)
    <br><small class="text-muted">
        {{ $banner->starts_at?->format('d/m/Y H:i') ?? 'Siempre' }} → {{ $banner->ends_at?->format('d/m/Y H:i') ?? 'sin fin' }}
    </small>
@endif
