<table class="table table-hover mb-0">
    <thead>
    <tr>
        <th>Artículo</th>
        <th>Deporte</th>
        <th class="text-right">Precio</th>
        <th class="text-center">Cantidad</th>
        @if ($editable ?? false)<th class="text-right">Acción</th>@endif
    </tr>
    </thead>
    <tbody>
    @forelse ($court->rentalItems as $item)
        <tr class="{{ $item->is_active ? '' : 'text-muted' }}">
            <td>
                {{ $item->name }}
                @unless ($item->is_active)
                    <span class="badge badge-secondary ml-1">Inactivo</span>
                @endunless
                @if ($item->description)
                    <div class="small text-muted">{{ $item->description }}</div>
                @endif
            </td>
            <td><span class="badge badge-info">{{ $item->sport?->name ?? '—' }}</span></td>
            <td class="text-right text-nowrap">{{ $item->priceLabel() }}</td>
            <td class="text-center">{{ $item->stock ?? '∞' }}</td>
            @if ($editable ?? false)
                <td class="text-right action-buttons text-nowrap">
                    @can('rental_items.update')
                        @php
                            $rentalPayload = [
                                'id' => $item->id,
                                'name' => $item->name,
                                'sport_id' => $item->sport_id,
                                'description' => $item->description,
                                'price' => $item->price,
                                'price_type' => $item->price_type,
                                'stock' => $item->stock,
                                'is_active' => $item->is_active,
                                'action' => route('courts.rental-items.update', [$court, $item]),
                            ];
                        @endphp
                        <button type="button" class="btn btn-link text-primary" title="Editar" data-edit-rental="{{ json_encode($rentalPayload) }}">
                            <i class="fas fa-edit"></i>
                        </button>
                    @endcan
                    @can('rental_items.destroy')
                        <form method="POST" action="{{ route('courts.rental-items.destroy', [$court, $item]) }}" class="d-inline" data-confirm="¿Eliminar {{ $item->name }}? Las reservas que ya lo incluyen lo conservan.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    @endcan
                </td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="5" class="text-center text-muted py-3">
                Aún no hay artículos en alquiler.
                @if ($editable ?? false)
                    <br><small>Pelotas, raquetas, pecheras... el jugador puede sumarlos al reservar.</small>
                @endif
            </td>
        </tr>
    @endforelse
    </tbody>
</table>
