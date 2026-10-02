<table class="table table-hover mb-0">
    <thead>
    <tr>
        <th>Nombre</th>
        <th>Deportes</th>
        <th class="text-right">Precio / hora</th>
        @if ($editable ?? false)<th class="text-right">Acción</th>@endif
    </tr>
    </thead>
    <tbody>
    @forelse ($court->fields as $field)
        <tr>
            <td>
                {{ $field->name }}
                @if ($field->dimensions)
                    <small class="text-muted">· {{ $field->dimensions }}</small>
                @endif
                @if ($field->features->isNotEmpty())
                    <div>
                        @foreach ($field->features as $feature)
                            <span class="badge badge-light border" title="{{ $feature->name }}">@if ($feature->icon)<i class="{{ $feature->icon }} mr-1"></i>@endif{{ $feature->name }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($field->description)
                    <div class="small text-muted text-wrap" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($field->description, 140) }}</div>
                @endif
            </td>
            <td>
                @foreach ($field->sports as $sport)
                    <span class="badge badge-info">{{ $sport->name }}</span>
                @endforeach
            </td>
            <td class="text-right">
                Bs {{ number_format((float) $field->price_per_hour, 2) }}
                @if ($field->offersAirConditioning())
                    <div class="small text-muted text-nowrap"><i class="fas fa-snowflake mr-1"></i>+ Bs {{ number_format((float) $field->air_conditioning_price, 2) }} con aire</div>
                @endif
                @if ($field->chargesLighting())
                    <div class="small text-muted text-nowrap"><i class="fas fa-lightbulb mr-1"></i>+ Bs {{ number_format((float) $field->lighting_price, 2) }} desde {{ substr($field->lighting_from, 0, 5) }}</div>
                @endif
            </td>
            @if ($editable ?? false)
                <td class="text-right action-buttons">
                    @can('court_fields.update')
                        @php
                            $fieldPayload = [
                                'id' => $field->id,
                                'name' => $field->name,
                                'price_per_hour' => $field->price_per_hour,
                                'dimensions' => $field->dimensions,
                                'description' => $field->description,
                                'features' => $field->features->pluck('key'),
                                'air_conditioning_price' => $field->air_conditioning_price,
                                'lighting_price' => $field->lighting_price,
                                'lighting_from' => $field->lighting_from ? substr($field->lighting_from, 0, 5) : null,
                                'sports' => $field->sports->pluck('id'),
                                'action' => route('courts.fields.update', [$court, $field]),
                            ];
                        @endphp
                        <button type="button" class="btn btn-link text-primary" title="Editar"
                                data-edit-field="{{ json_encode($fieldPayload) }}">
                            <i class="fas fa-edit"></i>
                        </button>
                    @endcan
                    @can('court_fields.destroy')
                        <form method="POST" action="{{ route('courts.fields.destroy', [$court, $field]) }}" class="d-inline" data-confirm="¿Eliminar {{ $field->name }}? Se borrarán también sus reservas.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    @endcan
                </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="4" class="text-center text-muted py-3">Aún no hay canchas físicas.</td></tr>
    @endforelse
    </tbody>
</table>
