<?php

namespace Gametech\Admin\Transformers;


use Gametech\Payment\Contracts\BankPayment;
use League\Fractal\TransformerAbstract;

class BankinTransformer extends TransformerAbstract
{



    public function transform(BankPayment $model)
    {


        return [
            'code' => (int) $model->code,
            'bankcode' => (!is_null($model->bank_account)  ? (!is_null($model->bank_account->bank) ? core()->displayBank($model->bank_account->bank->shortcode,$model->bank_account->bank->filepic) : '') : ''),
            'acc_no' => ( !is_null($model->bank_account) ? $model->bank_account->acc_no : ''),
            'bank_time' => $model->bank_time->format('d/m/y H:i:s'),
            'user_name' => (is_null($model->member) ? '' : $model->member->user_name),
            'channel' => $model->channel,
            'detail' => $model->detail.' <code>'. (!$model->remark_admin ? ($model->autocheck == 'Y' ? $model->user_create : '') : $model->remark_admin) .'</code>',
            'value' => '<span style="color:blue">'.$model->value.'</span>',
            'date' => $model->date_create->format('d/m/y H:i:s'),
            'confirm' => view('admin::module.bank_in.datatables_refill', ['code' => $model->code , 'status' => $model->status ])->render(),
            'edit' => view('admin::module.bank_in.datatables_edit', ['code' => $model->code , 'status' => $model->status])->render(),
            'cancel' => view('admin::module.bank_in.datatables_clear', ['code' => $model->code , 'status' => $model->status])->render(),
            'delete' => view('admin::module.bank_in.datatables_delete', ['code' => $model->code , 'status' => $model->status])->render()
        ];
    }


}
