{{--
    Venue managers. With $editable and the assignManagers ability, the owner can add (by email) and remove them.
--}}
@php($canAssign = ($editable ?? false) && auth()->user()->can('assignManagers', $court))

<div class="card card-warning card-outline">
    <div class="card-header">
        <h3 class="card-title">Managers</h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <tbody>
            @forelse ($court->managers as $manager)
                <tr>
                    <td>
                        {{ $manager->name }}
                        <div class="small text-muted">{{ $manager->email }}</div>
                    </td>
                    @if ($canAssign)
                        <td class="text-right action-buttons">
                            <form method="POST" action="{{ route('courts.managers.destroy', [$court, $manager]) }}" class="d-inline"
                                  data-confirm="¿Quitar a {{ $manager->name }} como manager de {{ $court->name }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-link text-danger" title="Quitar"><i class="fas fa-user-minus"></i></button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td class="text-center text-muted py-3">Aún no hay managers asignados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($canAssign)
        <div class="card-footer">
            @include('partials.errors', ['bag' => 'manager'])
            <form method="POST" action="{{ route('courts.managers.store', $court) }}">
                @csrf
                <label for="manager_email" class="small mb-1">Asignar manager por email</label>
                <div class="input-group">
                    <input id="manager_email" name="email" type="email" class="form-control" placeholder="usuario@correo.com"
                           value="{{ $errors->manager->any() ? old('email') : '' }}" required>
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-user-plus"></i> Asignar</button>
                    </div>
                </div>
                <small class="text-muted">
                    Debe ser un usuario registrado. Recibe el rol <code>manager</code>: ve y edita este centro y sus canchas,
                    pero no puede eliminarlos ni asignar managers.
                </small>
            </form>
        </div>
    @endif
</div>
