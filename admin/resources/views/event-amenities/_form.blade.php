@include('partials.errors')
@csrf
<div class="form-group">
    <label for="name">Nombre *</label>
    <input id="name" name="name" maxlength="100" class="form-control @error('name') is-invalid @enderror @error('key') is-invalid @enderror" value="{{ old('name', $amenity->name) }}" placeholder="Ej: Proyector" required>
    @if ($amenity->exists)
        <small class="text-muted">Clave: <code>{{ $amenity->key }}</code> (no cambia al renombrar).</small>
    @endif
</div>
<div class="form-group">
    <label for="icon">Ícono</label>
    <div class="input-group">
        <div class="input-group-prepend"><span class="input-group-text"><i id="icon-preview" class="{{ old('icon', $amenity->icon) ?: 'fas fa-question' }}"></i></span></div>
        <input id="icon" name="icon" maxlength="50" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon', $amenity->icon) }}" placeholder="Ej: fas fa-video">
    </div>
    <small class="text-muted">Clase de <a href="https://fontawesome.com/v5/search?m=free" target="_blank" rel="noopener">Font Awesome 5</a>. Opcional.</small>
</div>

@push('scripts')
    <script>
        $('#icon').on('input', function () {
            $('#icon-preview').attr('class', this.value.trim() || 'fas fa-question');
        });
    </script>
@endpush
