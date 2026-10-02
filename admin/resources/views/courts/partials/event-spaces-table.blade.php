<ul class="list-group list-group-flush">
    @forelse ($court->eventSpaces as $space)
        <li class="list-group-item d-flex align-items-start {{ $space->is_active ? '' : 'bg-light' }}">
            @if ($space->photoUrl())
                <img src="{{ $space->photoUrl() }}" alt="{{ $space->name }}" loading="lazy" class="rounded mr-3" style="width: 84px; height: 64px; object-fit: cover;">
            @else
                <div class="rounded mr-3 d-flex align-items-center justify-content-center bg-light text-muted" style="width: 84px; height: 64px;">
                    <i class="{{ $space->type->icon() }} fa-lg"></i>
                </div>
            @endif
            <div class="flex-grow-1 min-width-0">
                <div>
                    <strong>{{ $space->name }}</strong>
                    <small class="text-muted">· {{ $space->type->label() }}</small>
                    @unless ($space->is_active)
                        <span class="badge badge-secondary ml-1">Inactivo</span>
                    @endunless
                </div>
                <div class="small">
                    <i class="fas fa-users text-muted"></i> Hasta {{ $space->capacity }} personas
                    · Bs {{ number_format((float) $space->price_per_hour, 2) }}/h
                    @if ($space->min_hours > 1)
                        · mín. {{ $space->min_hours }} h
                    @endif
                    · {{ $space->openingTime() }}–{{ $space->closingTime() }}
                </div>
                @if ($space->amenities->isNotEmpty())
                    <div class="mt-1">
                        @foreach ($space->amenities as $amenity)
                            <span class="badge badge-light border font-weight-normal">@if ($amenity->icon)<i class="{{ $amenity->icon }} mr-1 text-muted"></i>@endif{{ $amenity->name }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($space->description)
                    <div class="small text-muted text-wrap mt-1" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($space->description, 140) }}</div>
                @endif
            </div>
            @if ($editable ?? false)
                <div class="action-buttons text-nowrap ml-2">
                    @can('event_spaces.update')
                        @php
                            $spacePayload = [
                                'id' => $space->id,
                                'name' => $space->name,
                                'type' => $space->type->value,
                                'description' => $space->description,
                                'price_per_hour' => $space->price_per_hour,
                                'capacity' => $space->capacity,
                                'min_hours' => $space->min_hours,
                                'amenities' => $space->amenities->pluck('key'),
                                'rules' => $space->rules,
                                'opening_time' => $space->opening_time ? substr($space->opening_time, 0, 5) : '',
                                'closing_time' => $space->closing_time ? substr($space->closing_time, 0, 5) : '',
                                'is_active' => $space->is_active,
                                'photo_url' => $space->photoUrl(),
                                'action' => route('courts.event-spaces.update', [$court, $space]),
                            ];
                        @endphp
                        <button type="button" class="btn btn-link text-primary" title="Editar" data-edit-space="{{ json_encode($spacePayload) }}">
                            <i class="fas fa-edit"></i>
                        </button>
                    @endcan
                    @can('event_spaces.destroy')
                        <form method="POST" action="{{ route('courts.event-spaces.destroy', [$court, $space]) }}" class="d-inline" data-confirm="¿Eliminar {{ $space->name }}? Se borrará también su historial de reservas.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    @endcan
                </div>
            @endif
        </li>
    @empty
        <li class="list-group-item text-center text-muted py-3">
            Aún no hay espacios para eventos.
            @if ($editable ?? false)
                <br><small>Parrilleros, quinchos o salones que tus clientes pueden alquilar por hora para reuniones y fiestas.</small>
            @endif
        </li>
    @endforelse
</ul>
