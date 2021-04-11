<?php

namespace Gametech\Admin\DataTables;




use Gametech\Admin\Transformers\SpinTransformer;
use Gametech\Core\Contracts\Spin;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;


class SpinDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query)
    {
        $dataTable = new EloquentDataTable($query);

        return $dataTable
            ->setTransformer(new SpinTransformer);

    }


    /**
     * @param Spin $model
     * @return mixed
     */
    public function query(Spin $model)
    {
        return $model->newQuery()
            ->select('spins_new.*');


    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return Builder
     */
    public function html()
    {
        return $this->builder()
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'dom'       => 'Bfrtip',

                'processing' => true,
                'serverSide' => true,
                'responsive' => false,
                'stateSave' => true,
                'paging' => true,
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
'ordering' => true,
                'autoWidth' => false,
                'scrollX' => true,
                'order'     => [[0, 'asc']],
                'buttons'   => [
                    'pageLength'
                ],
                'columnDefs' => [
                    [ 'targets' => '_all' , 'className' => 'text-nowrap']
                ]
            ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            ['data' => 'code' , 'name' => 'spins_new.code' , 'title' => '#' , 'orderable' => true , 'searchable' => true , 'className' => 'text-center text-nowrap'],
            ['data' => 'filepic' , 'name' => 'spins.filepic' , 'title' => 'ภาพ' , 'orderable' => false , 'searchable' => true , 'className' => 'text-center text-nowrap' ],
            ['data' => 'types' , 'name' => 'spins.name' , 'title' => 'ประเภทรางวัล' , 'orderable' => false , 'searchable' => true , 'className' => 'text-left text-nowrap' ],
            ['data' => 'name' , 'name' => 'spins.name' , 'title' => 'ขื่อรางวัล' , 'orderable' => false , 'searchable' => true , 'className' => 'text-left text-nowrap' ],
            ['data' => 'amount' , 'name' => 'spins.amount' , 'title' => 'จำนวนที่ได้' , 'orderable' => false , 'searchable' => true, 'className' => 'text-center text-nowrap' ],
            ['data' => 'winloss' , 'name' => 'spins.winloss' , 'title' => 'โอกาสออก' , 'orderable' => false , 'searchable' => true, 'className' => 'text-center text-nowrap' ],
            ['data' => 'action' , 'name' => 'action' , 'title' => 'Action' , 'orderable' => false , 'searchable' => false, 'className' => 'text-center text-nowrap', 'width' => '3%' ],
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'bankin_datatable_' . time();
    }
}
