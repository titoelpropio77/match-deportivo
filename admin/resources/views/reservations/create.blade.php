@extends('layouts.admin')

@section('title', 'Registrar reserva')
@section('page_title', 'Reservas')
@section('page_subtitle', 'Registrar reserva')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reservations.index') }}">Reservas</a></li>
    <li class="breadcrumb-item active">Registrar</li>
@endsection

@php
    $fieldOptions = $courts->flatMap(fn ($court) => $court->fields->map(fn ($field) => [
        'id' => $field->id,
        'price' => (float) $field->price_per_hour,
        'ac_price' => $field->offersAirConditioning() ? (float) $field->air_conditioning_price : null,
        'lighting_price' => $field->chargesLighting() ? (float) $field->lighting_price : null,
        'lighting_from' => $field->chargesLighting() ? substr($field->lighting_from, 0, 5) : null,
        'opening' => substr($court->opening_time, 0, 5),
        'closing' => substr($court->closing_time, 0, 5),
        'sports' => $field->sports->map(fn ($sport) => ['id' => $sport->id, 'name' => $sport->name])->values(),
        'rentals' => $court->rentalItems->map(fn ($item) => [
            'id' => $item->id,
            'sport_id' => $item->sport_id,
            'name' => $item->name,
            'price' => (float) $item->price,
            'flat' => $item->price_type === 'flat',
            'label' => $item->priceLabel(),
        ])->values(),
    ]))->values();
    $oldRentals = old('rentals', []);
    $selectedField = old('court_field_id', $prefill['court_field_id'] ?? null);
@endphp

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('reservations.partials.tabs')
        </div>
        <form method="POST" action="{{ route('reservations.store') }}">
            @csrf
            <div class="card-body">
                @include('partials.errors')
                <p class="text-muted">Para reservas por teléfono o presenciales. El horario se bloquea en la app al instante.</p>

                <h5 class="mb-3">Horario</h5>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="court_field_id">Cancha *</label>
                        <select id="court_field_id" name="court_field_id" class="form-control @error('court_field_id') is-invalid @enderror" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($courts as $court)
                                @if ($court->fields->isNotEmpty())
                                    <optgroup label="{{ $court->name }}">
                                        @foreach ($court->fields as $field)
                                            <option value="{{ $field->id }}" @selected((string) $selectedField === (string) $field->id)>{{ $field->name }} — Bs {{ number_format((float) $field->price_per_hour, 0) }}/h</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="sport_id">Deporte *</label>
                        <select id="sport_id" name="sport_id" class="form-control @error('sport_id') is-invalid @enderror" required
                                data-selected="{{ old('sport_id') }}"></select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="date">Fecha *</label>
                        <input type="date" id="date" name="date" min="{{ today()->toDateString() }}" required
                               class="form-control @error('date') is-invalid @enderror"
                               value="{{ old('date', $prefill['date'] ?? today()->toDateString()) }}">
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="start_time">Hora de inicio *</label>
                        <select id="start_time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" required
                                data-selected="{{ old('start_time', $prefill['start_time'] ?? '') }}"></select>
                    </div>
                    <div class="col-md-1 form-group">
                        <label for="hours">Horas *</label>
                        <select id="hours" name="hours" class="form-control @error('hours') is-invalid @enderror" required>
                            @for ($i = 1; $i <= $maxHours; $i++)
                                <option value="{{ $i }}" @selected((int) old('hours', 1) === $i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div id="extras-block" class="d-none mb-3">
                    <div class="custom-control custom-checkbox" id="ac-option">
                        <input type="hidden" name="air_conditioning" value="0">
                        <input type="checkbox" class="custom-control-input" id="air_conditioning" name="air_conditioning" value="1" @checked(old('air_conditioning'))>
                        <label class="custom-control-label font-weight-normal" for="air_conditioning"><i class="fas fa-snowflake text-muted mr-1"></i>Con aire acondicionado <span class="text-muted" id="ac-price"></span></label>
                    </div>
                    <div class="small text-muted mt-1" id="lighting-note"><i class="fas fa-lightbulb mr-1"></i><span></span></div>
                </div>

                <div id="rentals-block" class="d-none">
                    <h5 class="mb-2 mt-2">Artículos en alquiler <small class="text-muted">(opcional)</small></h5>
                    <div id="rentals" class="row"></div>
                </div>

                <h5 class="mb-3 mt-2">Cliente</h5>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="customer_name">Nombre</label>
                        <input id="customer_name" name="customer_name" maxlength="255"
                               class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="customer_phone">Teléfono</label>
                        <input id="customer_phone" name="customer_phone" maxlength="30"
                               class="form-control @error('customer_phone') is-invalid @enderror" value="{{ old('customer_phone') }}" placeholder="70000000">
                    </div>
                    <div class="col-md-5 form-group">
                        <label for="user_email">Email de su cuenta en la app (opcional)</label>
                        <input type="email" id="user_email" name="user_email"
                               class="form-control @error('user_email') is-invalid @enderror" value="{{ old('user_email') }}">
                        <small class="text-muted">Si lo indicas, la reserva aparecerá en "Mis reservas" de su app.</small>
                    </div>
                </div>

                <h5 class="mb-3 mt-2">Pago</h5>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="payment">Pago *</label>
                        <select id="payment" name="payment" class="form-control @error('payment') is-invalid @enderror" required>
                            <option value="venue" @selected(old('payment', 'venue') === 'venue')>Pagará en el local</option>
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment') === $value)>Ya pagó · {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Total</label>
                        <div id="total" class="form-control-plaintext font-weight-bold">—</div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="notes">Notas</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('reservations.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Registrar reserva</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const fields = @json($fieldOptions);
            const $field = $('#court_field_id');
            const $sport = $('#sport_id');
            const $start = $('#start_time');
            const $hours = $('#hours');
            const oldRentals = @json((object) $oldRentals);

            function current() {
                return fields.find(function (field) { return String(field.id) === String($field.val()); });
            }

            function fillOptions($select, options) {
                const selected = $select.val() || $select.data('selected');
                $select.empty();
                options.forEach(function (option) {
                    $select.append($('<option>').val(option.value).text(option.label));
                });
                if (options.some(function (option) { return String(option.value) === String(selected); })) {
                    $select.val(String(selected));
                }
            }

            function refresh() {
                const field = current();
                if (!field) {
                    fillOptions($sport, []);
                    fillOptions($start, []);
                    $('#total').text('—');
                    return;
                }
                fillOptions($sport, field.sports.map(function (sport) { return { value: sport.id, label: sport.name }; }));

                const open = parseInt(field.opening, 10);
                const close = parseInt(field.closing, 10);
                const hours = [];
                for (let hour = open; hour < close; hour++) {
                    const label = String(hour).padStart(2, '0') + ':00';
                    hours.push({ value: label, label: label });
                }
                fillOptions($start, hours);
                renderExtras(field);
                renderRentals(field);
                updateTotal();
            }

            // Gear of the field's venue for the selected sport, keeping typed quantities.
            function renderRentals(field) {
                const typed = {};
                $('#rentals input').each(function () { typed[$(this).data('id')] = this.value; });
                const items = field.rentals.filter(function (item) { return String(item.sport_id) === String($sport.val()); });
                const $box = $('#rentals').empty();
                items.forEach(function (item) {
                    const value = typed[item.id] ?? oldRentals[item.id] ?? 0;
                    $box.append(
                        $('<div class="col-md-4 form-group">').append(
                            $('<label class="small mb-1">').text(item.name + ' · ' + item.label),
                            $('<input type="number" min="0" max="20" class="form-control form-control-sm">')
                                .attr('name', 'rentals[' + item.id + ']').data('id', item.id).data('item', item).val(value)
                        )
                    );
                });
                $('#rentals-block').toggleClass('d-none', items.length === 0);
            }

            // Hours of the range that end after the lights go on (same rule as CourtField::isLitHour).
            function litHours(field, hours) {
                if (!field.lighting_price || !$start.val()) return 0;
                const [h, m] = field.lighting_from.split(':').map(Number);
                const from = h * 60 + m;
                const first = parseInt($start.val(), 10);
                let lit = 0;
                for (let hour = first; hour < first + hours; hour++) {
                    if ((hour + 1) * 60 > from) lit++;
                }
                return lit;
            }

            // Air conditioning option and lighting note of the selected court.
            function renderExtras(field) {
                $('#ac-option').toggleClass('d-none', !field.ac_price);
                if (!field.ac_price) $('#air_conditioning').prop('checked', false);
                $('#ac-price').text(field.ac_price ? '(+ Bs ' + field.ac_price.toFixed(2) + ' / hora)' : '');
                $('#lighting-note').toggleClass('d-none', !field.lighting_price);
                if (field.lighting_price) {
                    $('#lighting-note span').text('Luz: + Bs ' + field.lighting_price.toFixed(2) + ' por hora desde las ' + field.lighting_from + ' (se suma automáticamente).');
                }
                $('#extras-block').toggleClass('d-none', !field.ac_price && !field.lighting_price);
            }

            function updateTotal() {
                const field = current();
                if (!field) return;
                const hours = parseInt($hours.val(), 10);
                let total = field.price * hours;
                if (field.ac_price && $('#air_conditioning').is(':checked')) total += field.ac_price * hours;
                total += (field.lighting_price || 0) * litHours(field, hours);
                $('#rentals input').each(function () {
                    const item = $(this).data('item');
                    const quantity = parseInt(this.value, 10) || 0;
                    total += item.price * quantity * (item.flat ? 1 : hours);
                });
                $('#total').text('Bs ' + total.toFixed(2));
            }

            $field.on('change', refresh);
            $hours.on('change', refresh);
            $sport.on('change', function () { const field = current(); if (field) { renderRentals(field); updateTotal(); } });
            $('#rentals').on('input change', 'input', updateTotal);
            $start.on('change', updateTotal);
            $('#air_conditioning').on('change', updateTotal);
            refresh();
        })();
    </script>
@endpush
