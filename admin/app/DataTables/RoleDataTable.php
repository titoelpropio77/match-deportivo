<?php

namespace App\DataTables;

use App\Http\Controllers\Settings\RoleController;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class RoleDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'roles-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (Role $role) => '<strong>'.e($role->name).'</strong>')
            ->editColumn('permissions_count', fn (Role $role) => in_array($role->name, RoleController::PROTECTED_ROLES, true)
                ? '<span class="badge badge-danger">todos</span>'
                : '<span class="badge badge-primary">'.$role->permissions_count.'</span>')
            ->editColumn('users_count', fn (Role $role) => '<span class="badge badge-secondary">'.$role->users_count.'</span>')
            ->orderColumn('permissions_count', 'permissions_count $1')
            ->orderColumn('users_count', 'users_count $1')
            ->editColumn('created_at', fn (Role $role) => $role->created_at?->diffForHumans())
            ->addColumn('action', fn (Role $role) => view('settings.roles.partials.actions', [
                'role' => $role,
                'protected' => in_array($role->name, RoleController::PROTECTED_ROLES, true),
            ])->render())
            ->rawColumns(['name', 'permissions_count', 'users_count', 'action']);
    }

    public function query(Role $model): Builder
    {
        return $model->newQuery()->withCount(['permissions', 'users']);
    }

    protected function defaultOrder(): array
    {
        return [0, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Rol'),
            Column::make('guard_name')->title('Guard'),
            Column::make('permissions_count')->title('Permisos')->searchable(false)->addClass('text-center'),
            Column::make('users_count')->title('Usuarios')->searchable(false)->addClass('text-center'),
            Column::make('created_at')->title('Creado')->searchable(false),
            $this->actionColumn('text-right'),
        ];
    }
}
