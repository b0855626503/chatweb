<?php

namespace Gametech\Admin\DataTables;


use Gametech\Admin\Transformers\MemberTransformer;
use Gametech\Member\Contracts\Member;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;


class MemberDataTable extends DataTable
{

    protected $fastExcel = true;

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query)
    {
        $config = core()->getConfigData();

        $prem = bouncer()->hasPermission('wallet.member.tel');

        $dataTable = new EloquentDataTable($query);

        return $dataTable
            ->setTransformer(new MemberTransformer($config,$prem));
//        return $dataTable->setTransformer(new WithdrawTransformer);
//        return $dataTables->addColumn('action', 'admins::withdraw.datatables_confirm');
//        return $dataTable
//            ->editColumn('member_acc', function($query) {
//            return $query->bankCode->shortcode.'['.$query->memberCode->acc_no.']';
//        });
    }


    /**
     * @param Member $model
     * @return mixed
     */
    public function query(Member $model)
    {


        return $model->newQuery()
            ->confirm()
            ->select('members.*')->with(['bank', 'up'])->withCount(['downs' => function ($model) {
                $model->active();
            }])->withCasts([
                'date_regis' => 'date:Y-m-d'

            ]);

//        return $model->newQuery()
//            ->confirm()->active()
//            ->select('members.*')->withCount(['downs' => function ($query) {
//                $query->active();
//            }])->with(['bank','up']);


//        return $model->newQuery()->withoutGlobalScope('code')
//            ->where('members.enable','Y')->where('members.confirm','Y')->where('members.code','<>',0)
//            ->select('members.*',DB::raw('count(member.upline_code) as downs_count'))
//            ->with(['bank','up'])->join('members as member','member.upline_code','=','members.code')
//            ->groupBy('member.upline_code')
//            ->groupBy('members.code');

//        return $model->newQuery()->withoutGlobalScope('code')
//            ->where('members.enable','Y')->where('members.confirm','Y')->where('members.code','<>',0)
//            ->select(['members.*',DB::raw('count(downlines.upline_code) as downs_count')])
//            ->with(['bank','up'])->join('members as downlines', function ($join) {
//                $join->on('downlines.upline_code', '=', 'members.code')
//                    ->where('downlines.code', '<>', 0)
//                    ->where('downlines.enable', '=', 'Y')->groupBy('downlines.upline_code');
//            });

    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return Builder
     */
    public function html()
    {
        $prem = bouncer()->hasPermission('wallet.member.tel');
        if($prem){
            $btn = ['pageLength','excel'];
        }else{
            $btn = ['pageLength'];
        }


        return $this->builder()
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'dom' => 'Bfrtip',

                'processing' => true,
                'serverSide' => true,
                'responsive' => true,
                'stateSave' => true,
                'scrollX' => false,
                'paging' => true,
                'searching' => true,
                'deferRender' => true,
                'retrieve' => true,
                'ordering' => true,

                'pageLength' => 50,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [50, 100, 200],
                    ['50 rows', '100 rows', '200 rows']
                ],
                'buttons' => $btn,
                'columnDefs' => [
                    ['targets' => '_all', 'className' => 'text-center text-nowrap']
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
            ['data' => 'code', 'name' => 'members.code', 'title' => '#', 'orderable' => true, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'date', 'name' => 'members.date_regis', 'title' => 'วันที่สม้คร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'firstname', 'name' => 'members.firstname', 'title' => 'ชื่อ', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'lastname', 'name' => 'members.lastname', 'title' => 'นามสกุล', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'up', 'name' => 'members.upline_code', 'title' => 'Upline', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'down', 'name' => 'members.upline_code', 'title' => 'Downline', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'bank', 'name' => 'bank.shortcode', 'title' => 'ธนาคาร', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'acc_no', 'name' => 'members.acc_no', 'title' => 'เลขที่บัญชี', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'user_name', 'name' => 'members.user_name', 'title' => 'Username', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'pass', 'name' => 'members.user_pass', 'title' => 'รหัสผ่าน', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'lineid', 'name' => 'members.lineid', 'title' => 'ไอดีไลน์', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'tel', 'name' => 'members.tel', 'title' => 'เบอร์โทร', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'deposit', 'name' => 'members.count_deposit', 'title' => 'ฝาก', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'point', 'name' => 'members.point_deposit', 'title' => 'Point', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
            ['data' => 'diamond', 'name' => 'members.diamond', 'title' => 'Diamond', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
            ['data' => 'balance', 'name' => 'members.balance', 'title' => 'Wallet', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
            ['data' => 'pro', 'name' => 'members.promotion', 'title' => 'รับโปร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'enable', 'name' => 'members.enable', 'title' => 'เปิดใช้งาน', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'member_datatable_' . time();
    }

    public function fastExcelCallback()
    {


        return function ($row) {
            if($prem = bouncer()->hasPermission('wallet.member.tel')){
                return [
                    'Register Date' => $row['date_regis'],
                    'UserName' => $row['user_name'],
                    'FirstName' => $row['firstname'],
                    'LastName' => $row['lastname'],
                    'Line ID' => $row['lineid'],
                    'Mobile Number' => $row['tel'],
                ];
            }else{
                return [
                    'Register Date' => $row['date_regis'],
                    'UserName' => $row['user_name'],
                    'FirstName' => $row['firstname'],
                    'LastName' => $row['lastname'],
                    'Line ID' => $row['lineid']
                ];
            }

        };
    }
}
