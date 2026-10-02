@include('partials.errors')
@csrf
<h5 class="mb-3">Datos del torneo</h5>
<div class="row">
    <div class="col-md-6 form-group">
        <label for="name">Nombre *</label>
        <input id="name" name="name" maxlength="120" required class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $tournament->name) }}" placeholder="Ej.: Copa Primavera de Fútbol 5">
    </div>
    <div class="col-md-6 form-group">
        <label for="court_id">Centro deportivo *</label>
        <select id="court_id" name="court_id" required class="form-control @error('court_id') is-invalid @enderror">
            <option value="">— Selecciona —</option>
            @foreach ($courts as $court)
                <option value="{{ $court->id }}" @selected((string) old('court_id', $tournament->court_id) === (string) $court->id)>{{ $court->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label for="sport_id">Deporte *</label>
        <select id="sport_id" name="sport_id" required class="form-control @error('sport_id') is-invalid @enderror">
            <option value="">— Selecciona —</option>
            @foreach ($sports as $sport)
                <option value="{{ $sport->id }}" @selected((string) old('sport_id', $tournament->sport_id) === (string) $sport->id)>{{ $sport->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label for="format">Formato *</label>
        <select id="format" name="format" required class="form-control @error('format') is-invalid @enderror">
            @foreach ($formats as $value => $label)
                <option value="{{ $value }}" @selected(old('format', $tournament->format) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label for="gender">Categoría *</label>
        <select id="gender" name="gender" required class="form-control @error('gender') is-invalid @enderror">
            @foreach ($genders as $value => $label)
                <option value="{{ $value }}" @selected(old('gender', $tournament->gender) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label for="level_id">Nivel</label>
        <select id="level_id" name="level_id" class="form-control @error('level_id') is-invalid @enderror">
            <option value="">Todos los niveles</option>
            @foreach ($levels as $level)
                <option value="{{ $level->id }}" @selected((string) old('level_id', $tournament->level_id) === (string) $level->id)>{{ $level->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<h5 class="mb-3 mt-2">Inscripción y cupos</h5>
<div class="row">
    <div class="col-md-3 form-group">
        <label for="entry_fee">Costo por equipo (Bs) *</label>
        <input type="number" step="0.01" min="0" id="entry_fee" name="entry_fee" required
               class="form-control @error('entry_fee') is-invalid @enderror" value="{{ old('entry_fee', $tournament->entry_fee) }}">
        <small class="text-muted">0 = torneo gratuito. Se paga con QR al inscribir el equipo.</small>
    </div>
    <div class="col-md-3 form-group">
        <label for="max_teams">Cupo de equipos *</label>
        <input type="number" min="2" max="256" id="max_teams" name="max_teams" required
               class="form-control @error('max_teams') is-invalid @enderror" value="{{ old('max_teams', $tournament->max_teams) }}">
    </div>
    <div class="col-md-3 form-group">
        <label for="min_players_per_team">Mín. jugadores por equipo *</label>
        <input type="number" min="1" max="50" id="min_players_per_team" name="min_players_per_team" required
               class="form-control @error('min_players_per_team') is-invalid @enderror" value="{{ old('min_players_per_team', $tournament->min_players_per_team) }}">
    </div>
    <div class="col-md-3 form-group">
        <label for="max_players_per_team">Máx. jugadores por equipo</label>
        <input type="number" min="1" max="60" id="max_players_per_team" name="max_players_per_team"
               class="form-control @error('max_players_per_team') is-invalid @enderror" value="{{ old('max_players_per_team', $tournament->max_players_per_team) }}">
    </div>
</div>

<h5 class="mb-3 mt-2">Fechas</h5>
<div class="row">
    <div class="col-md-4 form-group">
        <label for="registration_closes_at">Cierre de inscripciones *</label>
        <input type="datetime-local" id="registration_closes_at" name="registration_closes_at" required
               class="form-control @error('registration_closes_at') is-invalid @enderror"
               value="{{ old('registration_closes_at', $tournament->registration_closes_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-4 form-group">
        <label for="starts_on">Inicio *</label>
        <input type="date" id="starts_on" name="starts_on" required class="form-control @error('starts_on') is-invalid @enderror"
               value="{{ old('starts_on', $tournament->starts_on?->toDateString()) }}">
    </div>
    <div class="col-md-4 form-group">
        <label for="ends_on">Fin (estimado)</label>
        <input type="date" id="ends_on" name="ends_on" class="form-control @error('ends_on') is-invalid @enderror"
               value="{{ old('ends_on', $tournament->ends_on?->toDateString()) }}">
    </div>
</div>

<h5 class="mb-3 mt-2">Información para los jugadores</h5>
<div class="row">
    <div class="col-md-6 form-group">
        <label for="description">Descripción</label>
        <textarea id="description" name="description" rows="4" maxlength="5000" class="form-control @error('description') is-invalid @enderror"
                  placeholder="Qué es el torneo, días de juego, a quién está dirigido...">{{ old('description', $tournament->description) }}</textarea>
    </div>
    <div class="col-md-6 form-group">
        <label for="prizes">Premios</label>
        <textarea id="prizes" name="prizes" rows="4" maxlength="2000" class="form-control @error('prizes') is-invalid @enderror"
                  placeholder="1.º lugar: trofeo + Bs 1000&#10;2.º lugar: medallas">{{ old('prizes', $tournament->prizes) }}</textarea>
    </div>
    <div class="col-md-12 form-group">
        <label for="rules">Reglamento</label>
        <textarea id="rules" name="rules" rows="5" maxlength="10000" class="form-control @error('rules') is-invalid @enderror"
                  placeholder="Duración de los partidos, tarjetas, walkover, desempates...">{{ old('rules', $tournament->rules) }}</textarea>
    </div>
    <div class="col-md-6 form-group">
        <label for="cover">Portada</label>
        @if ($tournament->cover_path)
            <div class="mb-2">
                <img src="{{ $tournament->coverUrl() }}" alt="Portada" class="rounded" style="max-height: 120px;">
                <div class="custom-control custom-checkbox mt-1">
                    <input type="checkbox" class="custom-control-input" id="remove_cover" name="remove_cover" value="1">
                    <label class="custom-control-label" for="remove_cover">Quitar portada</label>
                </div>
            </div>
        @endif
        <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp" class="form-control-file @error('cover') is-invalid @enderror">
        <small class="text-muted">JPG, PNG o WEBP de hasta 5 MB.</small>
    </div>
    <div class="col-md-6 form-group">
        <label for="status">Estado *</label>
        <select id="status" name="status" required class="form-control @error('status') is-invalid @enderror">
            @foreach ($statuses as $value => [$label])
                <option value="{{ $value }}" @selected(old('status', $tournament->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <small class="text-muted">En "Borrador" no se ve en la app. Con "Inscripciones abiertas" los capitanes pueden inscribir sus equipos.</small>
    </div>
</div>
