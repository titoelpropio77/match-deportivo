@include('partials.errors')
@csrf
<div class="row">
    <div class="col-md-6 form-group">
        <label for="name">Nombre *</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $court->name) }}" required>
    </div>
    <div class="col-md-6 form-group">
        <label for="owner_id">Partner (dueño de la cancha)</label>
        @can('courts.view_all')
            <select id="owner_id" name="owner_id" class="form-control @error('owner_id') is-invalid @enderror">
                <option value="">— Sin asignar —</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_id', $court->owner_id) === (string) $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                @endforeach
            </select>
            <small class="text-muted">Usuarios con rol <code>partner</code>. El partner solo verá y administrará sus canchas.</small>
        @else
            <input class="form-control" value="{{ $court->owner?->name ?? auth()->user()->name }}" disabled>
        @endcan
    </div>
    <div class="col-md-4 form-group">
        <label for="city_id">Ciudad *</label>
        <select id="city_id" name="city_id" class="form-control @error('city_id') is-invalid @enderror" required>
            <option value="">— Selecciona —</option>
            @foreach ($cities as $department => $departmentCities)
                <optgroup label="{{ $department }}">
                    @foreach ($departmentCities as $city)
                        <option value="{{ $city->id }}"
                                data-lat="{{ $city->latitude }}" data-lng="{{ $city->longitude }}"
                                @selected((string) old('city_id', $court->city_id) === (string) $city->id)>{{ $city->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>
    <div class="col-md-8 form-group">
        <label for="address">Dirección *</label>
        <input id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $court->address) }}" required>
    </div>
    @include('courts.partials.map-picker', ['editable' => true])
    <div class="col-md-6 form-group">
        <label for="opening_time">Apertura *</label>
        <input id="opening_time" name="opening_time" type="time" class="form-control @error('opening_time') is-invalid @enderror" value="{{ old('opening_time', substr((string) $court->opening_time, 0, 5)) }}" required>
    </div>
    <div class="col-md-6 form-group">
        <label for="closing_time">Cierre *</label>
        <input id="closing_time" name="closing_time" type="time" class="form-control @error('closing_time') is-invalid @enderror" value="{{ old('closing_time', substr((string) $court->closing_time, 0, 5)) }}" required>
    </div>
</div>

<label>Deportes que ofrece el complejo</label>
@php($selectedSports = array_map('intval', old('sports', $court->exists ? $court->sports->pluck('id')->all() : [])))
<div class="row">
    @foreach ($sports as $sport)
        <div class="col-md-3 col-6">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="sport_{{ $sport->id }}" name="sports[]" value="{{ $sport->id }}" @checked(in_array($sport->id, $selectedSports, true))>
                <label class="custom-control-label font-weight-normal" for="sport_{{ $sport->id }}">{{ $sport->name }}</label>
            </div>
        </div>
    @endforeach
</div>

