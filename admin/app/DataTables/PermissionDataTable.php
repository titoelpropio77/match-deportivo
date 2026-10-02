<?php

namespace App\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;

/**
 * Permission × role matrix: one checkbox column per role (`role_<id>`), rows grouped by module
 * (the name prefix, e.g. "courts" for "courts.update").
 */
class PermissionDataTable extends BaseDataTable
{
    /**
     * @var Collection<int, Role>|null
     */
    private ?Collection $roles = null;

    public function tableId(): string
    {
        return 'permissions-table';
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return $this->roles ??= Role::query()->orderBy('id')->get();
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        $canAssign = $this->user()->can('permissions.assign');

        $table = (new EloquentDataTable($query))
            ->addColumn('module', fn (Permission $permission) => Str::before($permission->name, '.'))
            ->filterColumn('module', function (Builder $query, string $keyword): void {
                $query->where('name', 'ilike', "{$keyword}%");
            })
            // The module is the name prefix, so sorting by name keeps each module together.
            ->orderColumn('module', 'name $1')
            ->addColumn('action', fn (Permission $permission) => view('settings.permissions.partials.actions', ['permission' => $permission])->render());

        foreach ($this->roles() as $role) {
            $table->addColumn('role_'.$role->id, fn (Permission $permission) => view('settings.permissions.partials.toggle', [
                'permission' => $permission,
                'role' => $role,
                'canAssign' => $canAssign,
            ])->render());
        }

        return $table->rawColumns([...$this->roles()->map(fn (Role $role) => 'role_'.$role->id)->all(), 'action']);
    }

    public function query(Permission $model): Builder
    {
        return $model->newQuery()->with('roles:id');
    }

    public function html(): HtmlBuilder
    {
        return parent::html()->addTableClass('permission-matrix');
    }

    protected function defaultOrder(): array
    {
        return [1, 'asc'];
    }

    protected function parameters(): array
    {
        return [
            'orderFixed' => [[0, 'asc']],
            'rowGroup' => ['dataSrc' => 'module'],
            'pageLength' => 25,
        ];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('module')->title('Módulo')->visible(false)->addClass('no-colvis'),
            Column::make('name')->title('Permiso'),
            Column::make('guard_name')->title('Guard'),
            ...$this->roles()->map(fn (Role $role) => Column::computed('role_'.$role->id, e($role->name))->addClass('text-center')->exportable(true)->printable(true))->all(),
            $this->actionColumn('text-right'),
        ];
    }
}
