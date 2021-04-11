<?php

namespace Gametech\Admin\Transformers;


use Gametech\Payment\Contracts\Withdraw;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class WithdrawTransformer extends TransformerAbstract
{



    public function transform(Withdraw $model)
    {

//        dd($model->toJson(JSON_PRETTY_PRINT));

        $status = ['0' => 'รอดำเนินการ' , '1' => 'อนุมัติ' , '2' => 'ไม่อนุมัติ'];

        return [
            'code' => (int) $model->code,
            'acc_no' => (!is_null($model->bank) ? core()->displayBank($model->bank->shortcode.' ['.(is_null($model->member) ? '' : $model->member->acc_no).']',$model->bank->filepic) : ''),
            'amount' => '<span style="color:red">'.$model->amount.'</span>',
            'date' => $model->date_record->format('d/m/y'),
            'time' => $model->timedept,
            'username' => $model->member_user,
            'name' => (is_null($model->member) ? '' : $model->member->name),
            'ip' => '<span class="text-long" data-toggle="tooltip" title="'.$model->ip.'">'.Str::limit($model->ip,10).'</span>',
            'bonus' => (!is_null($model->bills->first()) ? (is_null($model->bills->first()->promotion) ? '' : $model->bills->first()->promotion['name_th']) . ' [' . $model->bills->first()->date_create->format('d/m/Y') . ']' : ''),
            'status' => $status[$model->status],
            'date_approve' => ($model->date_approve === '0000-00-00 00:00:00' || is_null($model->date_approve) ? '' : $model->date_approve->format('d/m/y H:i:s')),
            'emp_approve' => ($model->emp_approve == 0 ? '' : $model->admin->user_name),
            'waiting' => view('admin::module.withdraw.datatables_confirm', ['code' => $model->code , 'status' => $model->status ])->render(),
            'cancel' => view('admin::module.withdraw.datatables_cancel', ['code' => $model->code , 'status' => $model->status ])->render(),
            'delete' => view('admin::module.withdraw.datatables_delete', ['code' => $model->code , 'status' => $model->status ])->render()
        ];
    }


}
