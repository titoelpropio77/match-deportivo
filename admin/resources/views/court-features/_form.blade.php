@include('partials.errors')
@csrf
<div class="form-group">
    <label for="name">Nombre *</label>
    <input id="name" name="name" maxlength="100" class="form-control @error('name') is-invalid @enderror @error('key') is-invalid @enderror" value="{{ old('name', $feature->name) }}" placeholder="Ej: Estacionamiento" required>
    @if ($feature->exists)
        <small class="text-muted">Clave: <code>{{ $feature->key }}</code> (no cambia al renombrar).</small>
    @endif
</div>
<div class="form-group">
    <label for="icon">Ícono</label>
    <div class="input-group">
        <div class="input-group-prepend"><span class="input-group-text"><i id="icon-preview" class="{{ old('icon', $feature->icon) ?: 'fas fa-question' }}"></i></span></div>
        <input id="icon" name="icon" maxlength="50" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon', $feature->icon) }}" placeholder="Ej: fas fa-parking">
    </div>
    <small class="text-muted">Clase de <a href="https://fontawesome.com/v5/search?m=free" target="_blank" rel="noopener">Font Awesome 5</a>. Opcional.</small>
</div>
@if ($feature->isSystem())
    <p class="text-muted small"><i class="fas fa-info-circle"></i> Esta característica activa un recargo por hora que se configura en cada cancha.</p>
@endif

@push('scripts')
    <script>
        $('#icon').on('input', function () {
            $('#icon-preview').attr('class', this.value.trim() || 'fas fa-question');
        });
    </script>
@endpush
