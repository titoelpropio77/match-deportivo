@extends('layouts.admin')

@section('title', 'Registrar reserva de evento')
@section('page_title', 'Eventos')
@section('page_subtitle', 'Registrar reserva de evento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('event-reservations.index') }}">Eventos</a></li>
    <li class="breadcrumb-item active">Registrar</li>
@endsection

@php
    $spaceOptions = $courts->flatMap(fn ($court) => $court->eventSpaces->map(fn ($space) => [
        'id' => $space->id,
        'price' => (float) $space->price_per_hour,
        'capacity' => $space->capacity,
        'min_hours' => $space->min_hours,
        'opening' => $space->openingTime(),
        'closing' => $space->closingTime(),
    ]))->values();
    $selectedSpace = old('event_space_id', $prefill['event_space_id'] ?? null);
@endphp

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('event-reservations.partials.tabs')
        </div>
        <form method="POST" action="{{ route('event-reservations.store') }}">
            @csrf
            <div class="card-body">
                @include('partials.errors')
                @if ($spaceOptions->isEmpty())
                    <div class="alert alert-info mb-0">
                        Tus centros todavía no tienen espacios para eventos activos. Agrégalos desde <strong>Centros deportivos → Editar</strong>.
                    </div>
                @else
                    <p class="text-muted">Para reservas por teléfono o presenciales. El horario se bloquea en la app al instante.</p>

                    <h5 class="mb-3">Evento</h5>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="event_space_id">Espacio *</label>
                            <select id="event_space_id" name="event_space_id" class="form-control @error('event_space_id') is-invalid @enderror" required>
                                <option value="">— Selecciona —</option>
                                @foreach ($courts as $court)
                                    @if ($court->eventSpaces->isNotEmpty())
                                        <optgroup label="{{ $court->name }}">
                                            @foreach ($court->eventSpaces as $space)
                                                <option value="{{ $space->id }}" @selected((string) $selectedSpace === (string) $space->id)>{{ $space->name }} — Bs {{ number_format((float) $space->price_per_hour, 0) }}/h · {{ $space->capacity }} pers.</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
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
                                    data-selected="{{ old('start_time') }}"></select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="hours">Horas *</label>
                            <select id="hours" name="hours" class="form-control @error('hours') is-invalid @enderror" required
                                    data-selected="{{ old('hours') }}"></select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="guests">Personas *</label>
                            <input type="number" id="guests" name="guests" min="1" required
                                   class="form-control @error('guests') is-invalid @enderror" value="{{ old('guests') }}">
                            <small class="text-muted" id="capacity-hint"></small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="event_type">Tipo de evento</label>
                            <select id="event_type" name="event_type" class="form-control @error('event_type') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach (\App\Enums\EventKind::cases() as $kind)
                                    <option value="{{ $kind->value }}" @selected(old('event_type') === $kind->value)>{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </div>
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
                            <small class="text-muted">Si lo indicas, la reserva aparecerá en "Mis eventos" de su app.</small>
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
                @endif
            </div>
            @if ($spaceOptions->isNotEmpty())
                <div class="card-footer text-right">
                    <a href="{{ route('event-reservations.index') }}" class="btn btn-default">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Registrar reserva</button>
                </div>
            @endif
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const spaces = @json($spaceOptions);
            const maxHours = @json($maxHours);
            const $space = $('#event_space_id');
            const $start = $('#start_time');
            const $hours = $('#hours');

            function current() {
                return spaces.find(function (space) { return String(space.id) === String($space.val()); });
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
                const space = current();
                if (!space) {
                    fillOptions($start, []);
                    fillOptions($hours, []);
                    $('#total').text('—');
                    $('#capacity-hint').text('');
                    return;
                }
                const open = parseInt(space.opening, 10);
                const close = parseInt(space.closing, 10);
                const starts = [];
                for (let hour = open; hour + space.min_hours <= close; hour++) {
                    const label = String(hour).padStart(2, '0') + ':00';
                    starts.push({ value: label, label: label });
                }
                fillOptions($start, starts);

                const hours = [];
                for (let count = space.min_hours; count <= maxHours; count++) {
                    hours.push({ value: count, label: count });
                }
                fillOptions($hours, hours);

                $('#guests').attr('max', space.capacity);
                $('#capacity-hint').text('Máximo ' + space.capacity);
                $('#total').text('Bs ' + (space.price * parseInt($hours.val(), 10)).toFixed(2));
            }

            $space.on('change', refresh);
            $hours.on('change', refresh);
            refresh();
        })();
    </script>
@endpush
