<?php

namespace App\DataTables;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class BannerDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'banners-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('preview', fn (Banner $banner) => view('banners.partials.thumb', ['banner' => $banner])->render())
            ->editColumn('title', fn (Banner $banner) => '<strong>'.e($banner->title).'</strong>'
                .($banner->subtitle ? '<br><small class="text-muted">'.e($banner->subtitle).'</small>' : ''))
            ->editColumn('link_type', fn (Banner $banner) => e($banner->linkDescription()))
            ->addColumn('status', fn (Banner $banner) => view('banners.partials.status', ['banner' => $banner])->render())
            ->addColumn('action', fn (Banner $banner) => view('banners.partials.actions', ['banner' => $banner])->render())
            ->rawColumns(['preview', 'title', 'status', 'action']);
    }

    public function query(Banner $model): Builder
    {
        return $model->newQuery();
    }

    protected function defaultOrder(): array
    {
        return [0, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('sort_order')->title('Orden')->addClass('text-center'),
            Column::computed('preview')->title('Vista previa')->exportable(false)->printable(false),
            Column::make('title')->title('Título'),
            Column::make('link_type')->title('Lleva a'),
            Column::computed('status')->title('Estado'),
            $this->actionColumn('text-right'),
        ];
    }
}
