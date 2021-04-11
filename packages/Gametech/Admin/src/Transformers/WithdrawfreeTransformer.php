<?php

namespace Gametech\Admin\Transformers;


use Gametech\Payment\Contracts\WithdrawFree;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class WithdrawfreeTransformer extends TransformerAbstract
{



    public function transform(WithdrawFree $model)
    {



        return [
            'code' => (int) $model->code,
            'acc_no' => (!is_null($model->bank) ? core()->displayBank($model->bank->shortcode.' ['.(is_null($model->member) ? '' : $model->member->acc_no).']',$model->bank->filepic) : ''),
            'amount' => '<span style="color:red">'.$model->amount.'</span>',
            'date' => $model->date_record->format('d/m/y'),
            'time' => $model->timedept,
            'username' => $model->member_user,
            'name' => (is_null($model->member) ? '' : $model->member->name),
            'ip' => '<span class="text-long" data-toggle="tooltip" title="'.$model->ip.'">'.Str::limit($model->ip,10).'</span>',
            'waiting' => view('admin::module.withdraw_free.datatables_confirm', ['code' => $model->code , 'status' => $model->status])->render(),
            'cancel' => view('admin::module.withdraw_free.datatables_cancel', ['code' => $model->code , 'status' => $model->status])->render(),
            'delete' => view('admin::module.withdraw_free.datatables_delete', ['code' => $model->code , 'status' => $model->status])->render()
        ];
    }


}
