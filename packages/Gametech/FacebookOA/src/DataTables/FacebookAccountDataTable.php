<?php

namespace Gametech\FacebookOA\DataTables;

use Gametech\FacebookOA\Contracts\FacebookAccount;
use Gametech\FacebookOA\Transformers\FacebookAccountTransformer;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;

class FacebookAccountDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param  mixed  $query  Results from query() method.
     */
    public function dataTable($query): DataTableAbstract
    {
        $dataTable = new EloquentDataTable($query);

        return $dataTable->setTransformer(new FacebookAccountTransformer);

    }

    /**
     * @return mixed
     */
    public function query(FacebookAccount $model)
    {
        $status = request()->input('status');

        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');
        if (empty($startdate)) {
            $startdate = now()->toDateString().' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString().' 23:59:59';
        }

        return $model->newQuery()->select('facebook_accounts.*');
    }

    /**
     * Optional method if you want to use html builder.
     */
    public function html(): Builder
    {
        return $this->builder()
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'dom' => 'Bfrtip',
                'processing' => true,
                'serverSide' => true,
                'responsive' => false,
                'stateSave' => true,
                'scrollX' => true,
                'paging' => false,
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
                'ordering' => true,

                'pageLength' => 50,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [50, 100, 200, 500, 1000],
                    ['50 rows', '100 rows', '200 rows', '500 rows', '1000 rows'],
                ],
                'buttons' => [

                ],
                'columnDefs' => [
                    ['targets' => '_all', 'className' => 'text-nowrap'],
                ],
            ]);
    }

    /**
     * Get columns.
     */
    protected function getColumns(): array
    {
        return [
            ['data' => 'id', 'name' => 'facebook_accounts.id', 'title' => '#', 'orderable' => true, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'name', 'name' => 'facebook_accounts.name', 'title' => 'ชื่อ Facebook OA', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'channel_id', 'name' => 'facebook_accounts.channel_id', 'title' => 'Channel ID', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'status', 'name' => 'facebook_accounts.status', 'title' => 'สถานะ', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap', 'width' => '3%'],
        ];
    }

    /**
     * Get filename for export.
     */
    protected function filename(): string
    {
        return 'bankin_datatable_'.time();
    }
}
