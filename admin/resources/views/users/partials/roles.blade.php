@forelse ($user->roles as $role)
    <span class="badge {{ $role->name === 'superadmin' ? 'badge-danger' : ($role->name === 'admin' ? 'badge-primary' : 'badge-secondary') }}">{{ $role->name }}</span>
@empty
    <span class="text-muted">sin rol</span>
@endforelse
