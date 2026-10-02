<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class UserDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'users-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('roles', fn (User $user) => view('users.partials.roles', ['user' => $user])->render())
            ->filterColumn('roles', function (Builder $query, string $keyword): void {
                $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'ilike', "%{$keyword}%"));
            })
            ->editColumn('email', fn (User $user) => view('partials.email', ['email' => $user->email])->render())
            ->editColumn('updated_at', fn (User $user) => $user->updated_at?->diffForHumans())
            ->addColumn('action', fn (User $user) => view('users.partials.actions', ['user' => $user])->render())
            ->rawColumns(['roles', 'email', 'action']);
    }

    public function query(User $model): Builder
    {
        return $model->newQuery()
            ->select(['id', 'name', 'nickname', 'email', 'phone', 'updated_at'])
            ->with('roles:id,name');
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Nombre'),
            Column::make('nickname')->title('Nickname')->defaultContent('—'),
            Column::make('email')->title('Email'),
            Column::make('phone')->title('Teléfono')->defaultContent('—'),
            Column::make('roles')->title('Rol')->orderable(false),
            Column::make('updated_at')->title('Actualizado')->searchable(false),
            $this->actionColumn(),
        ];
    }
}
