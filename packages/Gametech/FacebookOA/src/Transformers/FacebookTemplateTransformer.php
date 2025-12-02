<?php

namespace Gametech\FacebookOA\Transformers;

use Gametech\FacebookOA\Contracts\FacebookTemplate;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class FacebookTemplateTransformer extends TransformerAbstract
{
    public function transform(FacebookTemplate $model): array
    {

        return [
            'id' => $model->id,
            'category' => $model->category,
            'key' => $model->key,
            'message' => Str::limit($model->message, 50),
            'description' => $model->description,
            'action' => view('admin::module.facebook_template.datatables_actions', ['code' => $model->id])->render(),
        ];
    }
}
