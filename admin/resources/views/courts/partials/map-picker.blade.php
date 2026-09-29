{{--
    Location picker. Editable: click or drag the marker to set latitude/longitude.
    Without GOOGLE_MAPS_API_KEY the inputs stay editable and the map is replaced by a notice.
--}}
@php
    $mapsKey = config('services.google_maps.key');
    $editable = $editable ?? true;
    $lat = old('latitude', $court->latitude ?? '-17.7833');
    $lng = old('longitude', $court->longitude ?? '-63.1821');
@endphp

<div class="col-12 form-group">
    @if ($editable)
        <label class="d-flex align-items-center flex-wrap">
            <span>Ubicación exacta *</span>
            <span class="ml-auto">
                @if ($mapsKey)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-map-search><i class="fas fa-search-location"></i> Buscar dirección</button>
                @endif
                <button type="button" class="btn btn-sm btn-primary" data-map-geolocate><i class="fas fa-crosshairs"></i> Usar mi geoposición</button>
            </span>
        </label>
    @endif

    @if ($mapsKey)
        <div id="court-map" class="court-map"
             data-lat="{{ $lat }}" data-lng="{{ $lng }}"
             data-editable="{{ $editable ? 1 : 0 }}"
             data-is-new="{{ $court->exists ? 0 : 1 }}"
             data-map-id="{{ config('services.google_maps.map_id') }}"></div>
        @if ($editable)
            <small class="text-muted">Haz clic en el mapa o arrastra el marcador hasta el punto exacto de la cancha.</small>
        @endif
    @else
        <div class="alert alert-warning mb-0">
            <i class="fas fa-map-marked-alt"></i> Google Maps no está configurado: agrega <code>GOOGLE_MAPS_API_KEY</code> en <code>admin/.env</code>.
            @if ($editable) Mientras tanto, ingresa la latitud y longitud manualmente. @endif
        </div>
    @endif
</div>

@if ($editable)
    <div class="col-md-6 form-group">
        <label for="latitude">Latitud</label>
        <input id="latitude" name="latitude" type="number" step="0.0000001" class="form-control @error('latitude') is-invalid @enderror"
               value="{{ $lat }}" data-is-new="{{ $court->exists ? 0 : 1 }}" required @readonly($mapsKey)>
    </div>
    <div class="col-md-6 form-group">
        <label for="longitude">Longitud</label>
        <input id="longitude" name="longitude" type="number" step="0.0000001" class="form-control @error('longitude') is-invalid @enderror"
               value="{{ $lng }}" required @readonly($mapsKey)>
    </div>
@else
    <input type="hidden" id="latitude" value="{{ $lat }}">
    <input type="hidden" id="longitude" value="{{ $lng }}">
@endif

@if ($mapsKey)
    @once
        @push('scripts')
            <script src="{{ asset('js/court-map.js') }}"></script>
            <script async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($mapsKey) }}&loading=async&callback=initCourtMap&libraries=marker&language=es&region=BO"></script>
        @endpush
    @endonce
@elseif ($editable)
    @once
        @push('scripts')
            <script src="{{ asset('js/court-map.js') }}"></script>
        @endpush
    @endonce
@endif
