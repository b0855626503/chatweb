<?php

namespace Gametech\Admin\DataTables;

use App\Exports\UsersExport;
use Gametech\Admin\Transformers\MemberTransformer;
use Gametech\Member\Contracts\Member;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;


class MemberDataTable extends DataTable
{
    protected $exportClass = UsersExport::class;
//    protected $fastExcel = true;

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query)
    {
        // ดึง config ครั้งเดียวเพื่อส่งเข้า Transformer
        $config = core()->getConfigData();

        // คำนวณสิทธิ์เป็น boolean ชัดเจน
        $canViewTel  = bouncer()->hasPermission('wallet.member.tel');
        $canViewPass = bouncer()->hasPermission('wallet.member.password');

        $dataTable = new EloquentDataTable($query);

        // ส่ง boolean เข้าไปแทนการส่ง $prem ที่ไม่ชัดเจน
        return $dataTable->setTransformer(
            new MemberTransformer($config, $canViewTel, $canViewPass)
        );
    }


    /**
     * @param Member $model
     * @return mixed
     */
    public function query(Member $model)
    {
        $user = request()->input('user_name');
        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');

        if (empty($startdate)) {
            $startdate = now()->subMonths(3)->startOfMonth()->startOfDay()->toDateString() . ' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString() . ' 23:59:59';
        }

        return $model->newQuery()
//            ->with(['member_remark' => function ($query) {
//                $query->orderBy('code', 'desc')->latest();
//            }])
            ->confirm()
            ->select('members.*')
            ->with(['up'])->withCount(['downs' => function ($model) {
                $model->active();
            }])
            ->withCasts([
                'date_regis' => 'date:Y-m-d'
            ])
//            ->select(['members.code','members.date_regis','members.firstname','members.lastname','members.upline_code','members.acc_no','members.user_name','members.user_pass','members.lineid','members.tel','members.count_deposit','members.point_deposit','members.diamond','members.balance','members.remark','members.enable','members.status_pro','members.confirm','members.date_create'])
            ->when($startdate, function ($query, $startdate) use ($enddate) {
                $query->whereBetween('members.date_create', array($startdate, $enddate));
            })->when($user, function ($query, $user) {
                $query->where('members.user_name', $user);
            });


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
        if ($prem) {
            $btn = ['pageLength', 'excel'];
        } else {
            $btn = ['pageLength'];
        }


        return $this->builder()
            ->columns($this->getColumns())
            ->ajaxWithForm('', '#frmsearch')
            ->parameters([
                'dom' => 'Bfrtip',

                'processing' => true,
                'serverSide' => true,
                'responsive' => true,
                'stateSave' => false,
                'paging' => true,
                'searching' => true,
                'deferRender' => true,
                'retrieve' => false,
                'ordering' => true,
                'scrollX' => false,
              //  'scrollY' => '400px',       // ความสูงตาราง (เช่น 400px)
//                'scrollCollapse' => true,
                'pageLength' => 50,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [50, 100, 200, 500, 1000],
                    ['50 rows', '100 rows', '200 rows', '500 rows', '1000 rows']
                ],
                'buttons' => $btn,
                'columnDefs' => [
                    ['targets' => '_all', 'className' => 'text-nowrap']
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
        $config = core()->getConfigData();
        if ($config->seamless == 'Y') {

            return [
                ['data' => 'code', 'name' => 'members.code', 'title' => '#', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'date_regis', 'name' => 'members.date_regis', 'title' => 'วันที่สม้คร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'firstname', 'name' => 'members.firstname', 'title' => 'ชื่อ', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'lastname', 'name' => 'members.lastname', 'title' => 'นามสกุล', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'up', 'name' => 'members.upline_code', 'title' => 'Upline', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
                ['data' => 'down', 'name' => 'members.upline_code', 'title' => 'Downline', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'bank', 'name' => 'bank.shortcode', 'title' => 'ธนาคาร', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
                ['data' => 'acc_no', 'name' => 'members.acc_no', 'title' => 'เลขที่บัญชี', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'user_name', 'name' => 'members.user_name', 'title' => 'UserName', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'pass', 'name' => 'members.user_pass', 'title' => 'รหัสผ่าน', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'lineid', 'name' => 'members.lineid', 'title' => 'ไอดีไลน์', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'tel', 'name' => 'members.tel', 'title' => 'เบอร์โทร', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
//                ['data' => 'wallet', 'name' => 'members.wallet_id', 'title' => 'Wallet ID', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'deposit', 'name' => 'members.count_deposit', 'title' => 'ฝาก(ครั้ง)', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'credit', 'name' => 'members.credit', 'title' => 'เครดิตสะสม', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'point', 'name' => 'members.point_deposit', 'title' => 'Point', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'diamond', 'name' => 'members.diamond', 'title' => 'Diamond', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'balance', 'name' => 'members.balance', 'title' => 'เครดิต', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'remark', 'name' => 'members.remark', 'title' => 'หมายเหตุ', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//                ['data' => 'pro', 'name' => 'members.promotion', 'title' => 'รับโปร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'newuser', 'name' => 'members.promotion', 'title' => 'โปรสมาชิกใหม่', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'enable', 'name' => 'members.enable', 'title' => 'เปิดใช้งาน', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ];

        } else if ($config->multigame_open == 'Y') {
            return [
                ['data' => 'code', 'name' => 'members.code', 'title' => '#', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'date_regis', 'name' => 'members.date_regis', 'title' => 'วันที่สม้คร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'firstname', 'name' => 'members.firstname', 'title' => 'ชื่อ', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'lastname', 'name' => 'members.lastname', 'title' => 'นามสกุล', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'up', 'name' => 'members.upline_code', 'title' => 'Upline', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
                ['data' => 'down', 'name' => 'members.upline_code', 'title' => 'Downline', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'bank', 'name' => 'bank.shortcode', 'title' => 'ธนาคาร', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
                ['data' => 'acc_no', 'name' => 'members.acc_no', 'title' => 'เลขที่บัญชี', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'user_name', 'name' => 'members.user_name', 'title' => 'Username', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'pass', 'name' => 'members.user_pass', 'title' => 'รหัสผ่าน', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'lineid', 'name' => 'members.lineid', 'title' => 'ไอดีไลน์', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'tel', 'name' => 'members.tel', 'title' => 'เบอร์โทร', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'wallet', 'name' => 'members.wallet_id', 'title' => 'Wallet ID', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],

                ['data' => 'deposit', 'name' => 'members.count_deposit', 'title' => 'ฝาก', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'point', 'name' => 'members.point_deposit', 'title' => 'Point', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'diamond', 'name' => 'members.diamond', 'title' => 'Diamond', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'balance', 'name' => 'members.balance', 'title' => 'Wallet', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'remark', 'name' => 'members.remark', 'title' => 'หมายเหตุ', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//              ['data' => 'pro', 'name' => 'members.promotion', 'title' => 'รับโปร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'newuser', 'name' => 'members.promotion', 'title' => 'โปรสมาชิกใหม่', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'enable', 'name' => 'members.enable', 'title' => 'เปิดใช้งาน', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ];
        } else {
            return [
                ['data' => 'code', 'name' => 'members.code', 'title' => '#', 'orderable' => true, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'date_regis', 'name' => 'members.date_regis', 'title' => 'วันที่สม้คร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'name', 'name' => 'members.name', 'title' => 'ชื่อ - นามสกุล', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
//                ['data' => 'firstname', 'name' => 'members.firstname', 'title' => 'ชื่อ', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
//                ['data' => 'lastname', 'name' => 'members.lastname', 'title' => 'นามสกุล', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'up', 'name' => 'members.upline_code', 'title' => 'Upline', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
                ['data' => 'down', 'name' => 'members.upline_code', 'title' => 'Downline', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'bank', 'name' => 'bank.shortcode', 'title' => 'ธนาคาร', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
                ['data' => 'acc_no', 'name' => 'members.acc_no', 'title' => 'เลขที่บัญชี', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'user_name', 'name' => 'members.user_name', 'title' => 'Username', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'pass', 'name' => 'members.user_pass', 'title' => 'รหัสผ่าน', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'game_user', 'name' => 'members.game_user', 'title' => 'ID GAME', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
                ['data' => 'tel', 'name' => 'members.tel', 'title' => 'เบอร์โทร', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
//                ['data' => 'wallet', 'name' => 'members.wallet_id', 'title' => 'Wallet ID', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
                ['data' => 'count_deposit', 'name' => 'members.count_deposit', 'title' => 'ฝาก (ครั้ง)', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'sum_deposit', 'name' => 'members.count_deposit', 'title' => 'ยอดฝาก (รวม)', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
                ['data' => 'sum_withdraw', 'name' => 'members.count_deposit', 'title' => 'ยอดถอน (รวม)', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//                ['data' => 'credit', 'name' => 'members.credit', 'title' => 'แต้ม', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//                ['data' => 'diamond', 'name' => 'members.diamond', 'title' => 'Diamond', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//                ['data' => 'balance', 'name' => 'members.balance', 'title' => 'ยอดคงเหลือ', 'orderable' => true, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//                ['data' => 'refer', 'name' => 'members.refer', 'title' => 'รู้จักเราจาก', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
//                ['data' => 'remark', 'name' => 'members.remark', 'title' => 'หมายเหตุ', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//                ['data' => 'pro', 'name' => 'members.promotion', 'title' => 'รับโปร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'newuser', 'name' => 'members.promotion', 'title' => 'โปรสมาชิกใหม่', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'enable', 'name' => 'members.enable', 'title' => 'เปิดใช้งาน', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
                ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ];
        }

    }

    public function fastExcelCallback()
    {


        return function ($row) {
            if ($prem = bouncer()->hasPermission('wallet.member.tel')) {
                return [
                    'Date Regis' => $row['date_regis'],
                    'UserName' => $row['user_name'],
                    'FirstName' => $row['firstname'],
                    'LastName' => $row['lastname'],
                    'Line ID' => $row['lineid'],
                    'Mobile Number' => $row['tel'],
                ];
            } else {
                return [
                    'Date Regis' => $row['date_regis'],
                    'UserName' => $row['user_name'],
                    'FirstName' => $row['firstname'],
                    'LastName' => $row['lastname'],
                    'Line ID' => $row['lineid']
                ];
            }

        };
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
}
