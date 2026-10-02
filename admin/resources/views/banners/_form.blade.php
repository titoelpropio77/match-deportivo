@include('partials.errors')
@csrf
@php
    $linkType = old('link_type', $banner->link_type);
    $color = old('background_color', $banner->background_color);
@endphp
<div class="row">
    <div class="col-lg-7">
        <div class="form-group">
            <label for="title">Título *</label>
            <input id="title" name="title" maxlength="80" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $banner->title) }}" placeholder="Ej: ¡Inscríbete a la Copa de Verano!" required>
        </div>
        <div class="form-group">
            <label for="subtitle">Subtítulo</label>
            <input id="subtitle" name="subtitle" maxlength="160" class="form-control @error('subtitle') is-invalid @enderror" value="{{ old('subtitle', $banner->subtitle) }}" placeholder="Ej: Bs 200 por equipo · cupos limitados">
        </div>
        <div class="row">
            <div class="col-md-6 form-group">
                <label for="button_label">Texto del botón</label>
                <input id="button_label" name="button_label" maxlength="30" class="form-control @error('button_label') is-invalid @enderror" value="{{ old('button_label', $banner->button_label) }}" placeholder="Ej: Ver torneo">
                <small class="text-muted">Vacío: el banner se abre tocándolo, sin botón.</small>
            </div>
            <div class="col-md-3 form-group">
                <label for="background_color">Color de fondo</label>
                <input type="color" id="background_color" name="background_color" class="form-control @error('background_color') is-invalid @enderror" value="{{ $color }}">
            </div>
            <div class="col-md-3 form-group">
                <label for="sort_order">Orden *</label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="9999" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $banner->sort_order) }}" required>
            </div>
        </div>

        <h6 class="mt-2"><i class="fas fa-link text-muted"></i> ¿A dónde lleva?</h6>
        <div class="row">
            <div class="col-md-6 form-group">
                <label for="link_type">Destino *</label>
                <select id="link_type" name="link_type" class="form-control @error('link_type') is-invalid @enderror">
                    @foreach (\App\Models\Banner::LINK_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($linkType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 form-group" data-link-target="tournament">
                <label for="tournament_id">Torneo *</label>
                <select id="tournament_id" name="tournament_id" class="form-control @error('link_id') is-invalid @enderror">
                    <option value="">— Selecciona —</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected($linkType === 'tournament' && (string) old('tournament_id', $banner->link_id) === (string) $tournament->id)>
                            {{ $tournament->name }} ({{ $tournament->statusLabel() }})
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Si el torneo está en borrador o cancelado, el banner no se mostrará.</small>
            </div>
            <div class="col-md-6 form-group" data-link-target="court">
                <label for="court_id">Centro deportivo *</label>
                <select id="court_id" name="court_id" class="form-control @error('link_id') is-invalid @enderror">
                    <option value="">— Selecciona —</option>
                    @foreach ($courts as $court)
                        <option value="{{ $court->id }}" @selected($linkType === 'court' && (string) old('court_id', $banner->link_id) === (string) $court->id)>{{ $court->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 form-group" data-link-target="url">
                <label for="link_url">Enlace *</label>
                <input type="url" id="link_url" name="link_url" maxlength="500" class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url', $banner->link_url) }}" placeholder="https://...">
                <small class="text-muted">Se abre en el navegador del teléfono.</small>
            </div>
        </div>

        <h6 class="mt-2"><i class="far fa-calendar-alt text-muted"></i> Publicación</h6>
        <div class="row">
            <div class="col-md-4 form-group">
                <div class="custom-control custom-switch mt-md-4">
                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $banner->is_active))>
                    <label class="custom-control-label" for="is_active">Activo</label>
                </div>
            </div>
            <div class="col-md-4 form-group">
                <label for="starts_at">Desde</label>
                <input type="datetime-local" id="starts_at" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $banner->starts_at?->format('Y-m-d\TH:i')) }}">
            </div>
            <div class="col-md-4 form-group">
                <label for="ends_at">Hasta</label>
                <input type="datetime-local" id="ends_at" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $banner->ends_at?->format('Y-m-d\TH:i')) }}">
            </div>
        </div>
        <small class="text-muted d-block mb-3">Fechas vacías: se muestra mientras esté activo.</small>
    </div>

    <div class="col-lg-5">
        <label>Vista previa</label>
        <div id="banner-preview" class="banner-preview mb-3" style="background-color: {{ $color }};@if ($banner->imageUrl()) background-image: url('{{ $banner->imageUrl() }}');@endif">
            <div class="banner-preview-content">
                <div class="banner-preview-title" data-preview="title">{{ old('title', $banner->title) ?: 'Título del banner' }}</div>
                <div class="banner-preview-subtitle" data-preview="subtitle">{{ old('subtitle', $banner->subtitle) }}</div>
                <span class="banner-preview-button" data-preview="button_label">{{ old('button_label', $banner->button_label) }}</span>
            </div>
        </div>

        <div class="form-group">
            <label for="image">Imagen</label>
            @if ($banner->image_path)
                <div class="custom-control custom-checkbox mb-1">
                    <input type="checkbox" class="custom-control-input" id="remove_image" name="remove_image" value="1">
                    <label class="custom-control-label" for="remove_image">Quitar imagen (se verá el color de fondo)</label>
                </div>
            @endif
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" class="form-control-file @error('image') is-invalid @enderror">
            <small class="text-muted">Opcional. JPG, PNG o WEBP de hasta 5 MB, horizontal (ej. 1200×600). El texto va encima con un oscurecido para que se lea.</small>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const $preview = $('#banner-preview');

            function toggleTargets() {
                const type = $('#link_type').val();
                $('[data-link-target]').each(function () {
                    $(this).toggle(this.dataset.linkTarget === type);
                });
            }

            function syncText(field) {
                const value = $('#' + field).val().trim();
                const fallback = field === 'title' ? 'Título del banner' : '';
                $preview.find('[data-preview="' + field + '"]').text(value || fallback).toggle(Boolean(value || fallback));
            }

            $('#link_type').on('change', toggleTargets);
            ['title', 'subtitle', 'button_label'].forEach(function (field) {
                $('#' + field).on('input', function () { syncText(field); });
                syncText(field);
            });
            $('#background_color').on('input', function () { $preview.css('background-color', this.value); });
            $('#image').on('change', function () {
                const file = this.files[0];
                if (file) {
                    $preview.css('background-image', 'url(' + URL.createObjectURL(file) + ')');
                }
            });
            $('#remove_image').on('change', function () {
                if (this.checked) {
                    $preview.css('background-image', 'none');
                }
            });
            toggleTargets();
        })();
    </script>
@endpush
