<?php

namespace Gametech\Admin\DataTables;




use Gametech\Admin\Transformers\RpCreditTransformer;
use Gametech\Member\Contracts\MemberFreeCredit;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;


class RpCreditDataTable extends DataTable
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
//            ->filter(function ($query) {
//                if (request()->input('credit_type')) {
//                    $query->where('credit_type', request('credit_type'));
//                }
//
//                if (request()->input('ip')) {
//                    $query->where('ip', 'like', "%" . request('ip') . "%");
//                }
//
//                if ($user = request()->input('user_name')) {
//                    $query->whereIn('members_freecredit.member_code', function ($query)  use ($user){
//                        $query->from('members')->select('members.code')->where('members.user_name', $user);
//                    });
//                }
//            })
            ->with('withdraw', function() use ($query) {
                return core()->currency((clone $query)->where('credit_type','W')->sum('credit_amount'));
            })
            ->with('deposit', function() use ($query) {
                return core()->currency((clone $query)->where('credit_type','D')->sum('credit_amount'));
            })
            ->setTransformer(new RpCreditTransformer);

    }


    /**
     * @param MemberFreeCredit $model
     * @return mixed
     */
    public function query(MemberFreeCredit $model)
    {
        $ip = request()->input('ip');
        $type = request()->input('credit_type');
        $user = request()->input('user_name');
        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');
        if(empty($startdate)){
            $startdate = now()->toDateString().' 00:00:00';
        }
        if(empty($enddate)){
            $enddate = now()->toDateString().' 23:59:59';
        }

        return $model->newQuery()
            ->with('member','admin')
            ->active()->notauto()
            ->select('members_freecredit.*')->withCasts([
                'date_create' => 'datetime:Y-m-d H:00'
            ])
            ->when($startdate, function ($query, $startdate) use ($enddate) {
                $query->whereBetween('date_create', array($startdate, $enddate));
            })
            ->when($type, function ($query, $type) {
                $query->where('credit_type',$type);
            })
            ->when($ip, function ($query, $ip) {
                $query->where('ip', 'like', "%" . $ip . "%");
            })
            ->when($user, function ($query, $user) {
                $query->whereIn('members_freecredit.member_code', function ($q) use ($user) {
                    $q->from('members')->select('members.code')->where('members.user_name', $user);
                });
            });




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
            ->ajaxWithForm('','#frmsearch')
            ->parameters([
                'dom'       => 'Bfrtip',
                'processing' => true,
                'serverSide' => true,
                'responsive' => false,
                'stateSave' => true,
                'scrollX' => true,

                'paging' => true,
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
'ordering' => true,
                'autoWidth' => false,
                'pageLength' => 50,
                'order'     => [[0, 'desc']],
                'lengthMenu' => [
                    [ 50, 100, 200 ],
                    [ '50 rows', '100 rows', '200 rows' ]
                ],
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
            ['data' => 'code' , 'name' => 'members_freecredit.code' , 'title' => '#' , 'orderable' => true , 'searchable' => false , 'className' => 'text-center text-nowrap'],
            ['data' => 'member_name' , 'name' => 'members_freecredit.member_name' , 'title' => 'สมาชิก' , 'orderable' => false , 'searchable' => false , 'className' => 'text-left text-nowrap' ],
            ['data' => 'user_name' , 'name' => 'members_freecredit.user_name' , 'title' => 'User ID' , 'orderable' => false , 'searchable' => false, 'className' => 'text-center text-nowrap' ],
            ['data' => 'credit_type' , 'name' => 'members_freecredit.credit_type' , 'title' => 'ประเภท' , 'orderable' => false , 'searchable' => false, 'className' => 'text-center text-nowrap' ],
            ['data' => 'credit_amount' , 'name' => 'members_freecredit.credit_amount' , 'title' => 'จำนวน' , 'orderable' => false , 'searchable' => false, 'className' => 'text-right text-nowrap' ],
            ['data' => 'credit_before' , 'name' => 'members_freecredit.credit_before' , 'title' => 'Credit (ก่อน)' , 'orderable' => false , 'searchable' => false, 'className' => 'text-right text-nowrap' ],
            ['data' => 'credit_balance' , 'name' => 'members_freecredit.credit_balance' , 'title' => 'Credit (หลัง)' , 'orderable' => false , 'searchable' => false, 'className' => 'text-right text-nowrap' ],
            ['data' => 'remark' , 'name' => 'members_freecredit.remark' , 'title' => 'หมายเหตุ' , 'orderable' => false , 'searchable' => false, 'className' => 'text-left text-nowrap' ],
            ['data' => 'emp_name' , 'name' => 'members_freecredit.emp_name' , 'title' => 'ผู้ทำรายการ' , 'orderable' => false , 'searchable' => false, 'className' => 'text-left text-nowrap' ],
            ['data' => 'date_create' , 'name' => 'members_freecredit.date_create' , 'title' => 'วันที่' , 'orderable' => false , 'searchable' => false, 'className' => 'text-center text-nowrap' ],
            ['data' => 'ip' , 'name' => 'members_freecredit.ip' , 'title' => 'ip' , 'orderable' => false , 'searchable' => false, 'className' => 'text-center text-nowrap' ],
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
