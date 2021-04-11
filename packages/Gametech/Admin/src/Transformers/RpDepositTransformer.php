<?php

namespace Gametech\Admin\Transformers;

use Gametech\Payment\Contracts\BankPayment;
use League\Fractal\TransformerAbstract;

class RpDepositTransformer extends TransformerAbstract
{


    public function transform(BankPayment $model)
    {


        return [
            'code' => (int)$model->code,
            'bank' => core()->displayBank($model->bank_account->bank->shortcode, $model->bank_account->bank->filepic),
            'acc_no' => $model->bank_account->acc_no,
            'date' => $model->bank_time->format('d/m/y H:i:s'),
            'date_approve' => (is_null($model->date_approve) ? '' : $model->date_approve->format('d/m/y H:i:s')),
            'channel' => $model->channel,
            'detail' => $model->detail,
            'amount' => core()->textcolor(core()->currency($model->value), 'text-success'),
            'member_name' => (is_null($model->member) ? '' : $model->member->name),
            'user_name' => (is_null($model->member) ? '' : $model->member->user_name),
            'remark' => $model->remark_admin,
            'emp_name' => ($model->emp_topup === 0 ? ($model->topup_by ? $model->topup_by : $model->create_by) : (is_null($model->admin) ? '' : $model->admin->user_name)),
            'ip' => ($model->emp_topup === 0 ? '127.0.0.1' : $model->ip_admin)
        ];
    }


}
