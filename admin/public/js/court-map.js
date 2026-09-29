/*
 * Google Maps location picker for courts.
 * Markup: #court-map[data-lat][data-lng][data-editable][data-map-id] plus #latitude / #longitude inputs.
 * The Maps script calls window.initCourtMap once loaded (see courts/partials/map-picker.blade.php).
 */
(function ($) {
    'use strict';

    const DECIMALS = 7;

    function toLiteral(position) {
        // AdvancedMarkerElement.position may be a LatLng or a LatLngLiteral.
        return typeof position.lat === 'function'
            ? { lat: position.lat(), lng: position.lng() }
            : { lat: position.lat, lng: position.lng };
    }

    function writeInputs(position) {
        $('#latitude').val(position.lat.toFixed(DECIMALS));
        $('#longitude').val(position.lng.toFixed(DECIMALS));
    }

    window.initCourtMap = async function () {
        const el = document.getElementById('court-map');
        if (!el) {
            return;
        }

        const { Map } = await google.maps.importLibrary('maps');
        const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');

        const editable = el.dataset.editable === '1';
        const start = {
            lat: parseFloat($('#latitude').val() || el.dataset.lat),
            lng: parseFloat($('#longitude').val() || el.dataset.lng),
        };

        const map = new Map(el, {
            center: start,
            zoom: 16,
            mapId: el.dataset.mapId,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            gestureHandling: editable ? 'greedy' : 'cooperative',
        });

        const marker = new AdvancedMarkerElement({
            map: map,
            position: start,
            gmpDraggable: editable,
            title: editable ? 'Arrastra para ubicar la cancha' : '',
        });

        if (!editable) {
            return;
        }

        function moveTo(position, pan) {
            marker.position = position;
            writeInputs(position);
            if (pan) {
                map.panTo(position);
            }
        }

        map.addListener('click', function (event) {
            moveTo(event.latLng.toJSON(), false);
        });

        marker.addListener('dragend', function () {
            writeInputs(toLiteral(marker.position));
        });

        // New courts start at the selected city's center; existing ones only pan the view.
        $('#city_id').on('change', function () {
            const option = this.selectedOptions[0];
            if (!option || !option.dataset.lat) {
                return;
            }
            const cityCenter = { lat: parseFloat(option.dataset.lat), lng: parseFloat(option.dataset.lng) };
            if (el.dataset.isNew === '1') {
                moveTo(cityCenter, true);
                map.setZoom(14);
            } else {
                map.panTo(cityCenter);
            }
        });

        $('[data-map-geolocate]').off('click').on('click', function () {
            geolocate(function (position) {
                moveTo(position, true);
                map.setZoom(17);
            });
        });

        $('[data-map-search]').on('click', function () {
            const address = $('#address').val().trim();
            if (!address) {
                toastr.warning('Escribe primero la dirección.');
                return;
            }
            const city = $('#city_id option:selected').text().trim();
            const query = [address, city, 'Bolivia'].filter(Boolean).join(', ');

            new google.maps.Geocoder().geocode({ address: query, region: 'BO' }, function (results, status) {
                if (status !== 'OK' || !results.length) {
                    toastr.error(status === 'REQUEST_DENIED'
                        ? 'La API key no tiene habilitada la "Geocoding API".'
                        : 'No se encontró la dirección. Ubica el punto manualmente en el mapa.');
                    return;
                }
                moveTo(results[0].geometry.location.toJSON(), true);
                map.setZoom(17);
            });
        });
    };

    /** Browser geolocation; also used without a Maps key to fill the inputs directly. */
    function geolocate(onSuccess) {
        if (!navigator.geolocation) {
            toastr.error('Tu navegador no soporta geolocalización.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function (result) {
                onSuccess({ lat: result.coords.latitude, lng: result.coords.longitude });
            },
            function () {
                toastr.error('No se pudo obtener tu ubicación (permiso denegado o no disponible).');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    // Fallback when the map is not available (no API key): geolocation fills the inputs.
    $(function () {
        $('[data-map-geolocate]').on('click', function () {
            if (window.google && window.google.maps) {
                return;
            }
            geolocate(writeInputs);
        });

        $('#city_id').on('change', function () {
            const option = this.selectedOptions[0];
            if (window.google && window.google.maps) {
                return;
            }
            if (option && option.dataset.lat && $('#latitude').data('is-new') === 1) {
                writeInputs({ lat: parseFloat(option.dataset.lat), lng: parseFloat(option.dataset.lng) });
            }
        });
    });

    // Invalid key / blocked referrer: Google calls this global hook.
    window.gm_authFailure = function () {
        $('#court-map').replaceWith(
            '<div class="alert alert-danger mb-0">No se pudo cargar Google Maps: revisa <code>GOOGLE_MAPS_API_KEY</code> ' +
            '(APIs habilitadas y referrer permitido). Puedes escribir la latitud y longitud manualmente.</div>'
        );
        $('#latitude, #longitude').prop('readonly', false);
    };
})(jQuery);
