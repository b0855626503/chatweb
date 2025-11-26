<?php

namespace Gametech\LineOA\Transformers;

use Gametech\LineOA\Contracts\LineAccount;
use Gametech\LineOA\Contracts\LineTemplate;
use League\Fractal\TransformerAbstract;

class LineTemplateTransformer extends TransformerAbstract
{
    public function transform(LineTemplate $model): array
    {

        return [
            'id' => $model->id,
            'category' => $model->category,
            'key' => $model->key,
            'message' => $model->message,
            'description' => $model->description,
            'action' => view('admin::module.line_template.datatables_actions', ['code' => $model->id])->render(),
        ];
    }
}
