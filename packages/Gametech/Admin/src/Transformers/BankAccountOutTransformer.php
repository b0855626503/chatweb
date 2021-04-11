<?php

namespace Gametech\Admin\Transformers;


use Gametech\Payment\Contracts\BankAccount;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class BankAccountOutTransformer extends TransformerAbstract
{



    public function transform(BankAccount $model)
    {


        return [
            'code' => (int) $model->code,
            'bank' => core()->displayBank($model->bank->name_th,$model->bank->filepic),
            'name' => '<span class="text-long" data-toggle="tooltip" title="'.$model->acc_name.'">'.Str::limit($model->acc_name,30).'</span>',
            'acc_no' => $model->acc_no,
            'username' => $model->user_name,
            'password' => $model->user_pass,
            'balance' => core()->textcolor(core()->currency($model->balance)),
            'sort' => $model->sort,
            'auto' => core()->displayBtn($model->code,$model->status_auto,'status_auto'),
            'topup' => core()->displayBtn($model->code,$model->status_topup,'status_topup'),
            'display' => core()->displayBtn($model->code,$model->display_wallet,'display_wallet'),
            'enable' => core()->displayBtn($model->code,$model->enable,'enable'),
            'action' => view('admin::module.bank_account_in.datatables_actions', ['code' => $model->code])->render(),
        ];
    }


}
