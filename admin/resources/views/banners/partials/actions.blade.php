<div class="text-right action-buttons">
    @can('banners.update')
        <a href="{{ route('banners.edit', $banner) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
    @endcan
    @can('banners.destroy')
        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                data-delete-url="{{ route('banners.destroy', $banner) }}"
                data-name="el banner &quot;{{ $banner->title }}&quot;"
                data-warning="Dejará de mostrarse en la app."
                data-table="banners-table"><i class="fas fa-trash-alt"></i></button>
    @endcan
</div>
