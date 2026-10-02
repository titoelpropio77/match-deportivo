<div class="text-right action-buttons">
    @can('court_features.update')
        <a href="{{ route('court-features.edit', $feature) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('court_features.destroy')
        @if ($feature->isSystem())
            <span class="btn btn-link text-muted disabled" title="Los recargos de las reservas dependen de ella"><i class="fas fa-lock"></i></span>
        @else
            <button type="button" class="btn btn-link text-danger" title="Eliminar"
                    data-delete-url="{{ route('court-features.destroy', $feature) }}"
                    data-name="la característica {{ $feature->name }}"
                    data-warning="Se quitará de {{ $feature->fields_count }} cancha(s)."
                    data-table="court-features-table"><i class="fas fa-trash-alt"></i></button>
        @endif
    @endcan
</div>
