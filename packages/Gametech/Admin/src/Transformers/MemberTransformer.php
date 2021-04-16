<?php

namespace Gametech\Admin\Transformers;


use Gametech\Member\Contracts\Member;
use League\Fractal\TransformerAbstract;
use Mozammil\Censor\Replacers\StarReplacer;


class MemberTransformer extends TransformerAbstract
{

    protected $config;

    protected $permiss;

    public function __construct($config, $permiss)
    {
        $this->config = $config;
        $this->permiss = $permiss;
    }


    public function transform(Member $model)
    {


//dd($model->banks_account->toJson(JSON_PRETTY_PRINT));

        $config = $this->config;
        $permiss = $this->permiss;

        if ($permiss) {
            $tel = $model->tel;
        } else {
            $tel = StarReplacer::replace($model->tel);
        }

        if ($config->pro_wallet === 'Y') {
            $pro = '';
        } else {
            $pro = '<button class="btn btn-xs icon-only ' . ($model->promotion == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->promotion) . "'" . "," . "'promotion'" . ')">' . ($model->promotion == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';

        }
        return [
            'code' => (int)$model->code,
            'date' => $model->date_regis->format('d/m/Y'),
            'firstname' => $model->firstname,
            'lastname' => $model->lastname,
            'up' => ($model->upline_code == 0 ? '' : (is_null($model->up) ? '' : $model->up->name)),
            'down' => $model->downs_count,
            'bank' => (is_null($model->bank) ? '' : core()->displayBank($model->bank->shortcode, $model->bank->filepic)),
            'acc_no' => $model->acc_no,
            'user_name' => $model->user_name,
            'tel' => $tel,
            'pass' => $model->user_pass,
            'lineid' => $model->lineid,
            'deposit' => $model->count_deposit,
            'point' => "<span class='text-primary'>" . $model->point_deposit . "</span>",
            'balance' => "<span class='text-success'>" . $model->balance . "</span>",
            'diamond' => "<span class='text-indigo'>" . $model->diamond . "</span>",
            'pro' => $pro,
            'enable' => '<button class="btn btn-xs icon-only ' . ($model->enable == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->enable) . "'" . "," . "'enable'" . ')">' . ($model->enable == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>',
            'action' => view('admin::module.member.datatables_actions', ['code' => $model->code])->render(),
        ];
    }


}
