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
            <td>{{ $field->name }}</td>
            <td>
                @foreach ($field->sports as $sport)
                    <span class="badge badge-info">{{ $sport->name }}</span>
                @endforeach
            </td>
            <td class="text-right">Bs {{ number_format((float) $field->price_per_hour, 2) }}</td>
            @if ($editable ?? false)
                <td class="text-right action-buttons">
                    @can('court_fields.update')
                        @php
                            $fieldPayload = [
                                'id' => $field->id,
                                'name' => $field->name,
                                'price_per_hour' => $field->price_per_hour,
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
