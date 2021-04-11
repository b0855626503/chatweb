<?php

namespace Gametech\Admin\Transformers;

use Gametech\LogAdmin\Contracts\Activity;
use Gametech\LogUser\Contracts\ActivityUser;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class RpStaffLogTransformer extends TransformerAbstract
{



    public function transform(Activity $model)
    {

//        dd($model->toJson(JSON_PRETTY_PRINT));

        return [
            'id' => (int) $model->id,
            'desctiption' => $model->description,
            'detail' => $model->details,
            'user_name' => (is_null($model->admin)  ? '-' : $model->admin->user_name),
            'member_name' => (is_null($model->admin)  ? 'Guest' : $model->admin->name.' '.$model->admin->surname),
            'route' => '<span class="text-long" data-toggle="tooltip" title="'.$model->route.'">'.Str::limit($model->route,50).'</span>',
            'time' => $model->created_at->diffForHumans(),
            'ip' => $model->ipAddress
        ];
    }


}
