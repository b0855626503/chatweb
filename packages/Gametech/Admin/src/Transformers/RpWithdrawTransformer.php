<?php

namespace Gametech\Admin\Transformers;

use Gametech\Payment\Contracts\Withdraw;
use League\Fractal\TransformerAbstract;

class RpWithdrawTransformer extends TransformerAbstract
{



    public function transform(Withdraw $model)
    {

        $status = ['0' => 'รอดำเนินการ' , '1' => 'อนุมัติ' , '2' => 'ไม่อนุมัติ'];

        return [
            'code' => (int) $model->code,
            'bank' => core()->displayBank($model->bank->shortcode,$model->bank->filepic),
            'date' => $model->date_record->format('d/m/y'),
            'time' => $model->timedept,
            'amount' => core()->textcolor(core()->currency($model->amount),'text-danger'),
            'member_name' => (is_null($model->member)  ? '' : $model->member->name),
            'user_name' => (is_null($model->member)  ? '' : $model->member->user_name),
            'status' => $status[$model->status],
            'date_approve' => ($model->date_approve === '0000-00-00 00:00:00' || is_null($model->date_approve) ? '' : core()->formatDate($model->date_approve,'d/m/y H:i:s')),

            'remark' => $model->remark,
            'emp_name' => ($model->emp_approve === 0 ? '' : (is_null($model->admin) ? '' : $model->admin->name)),
            'date_create' => $model->date_create->format('d/m/y H:i:s'),
            'ip' => $model->ip
        ];
    }


}
