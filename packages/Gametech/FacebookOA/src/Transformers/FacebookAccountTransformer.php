<?php

namespace Gametech\FacebookOA\Transformers;

use Gametech\FacebookOA\Contracts\FacebookAccount;
use League\Fractal\TransformerAbstract;

class FacebookAccountTransformer extends TransformerAbstract
{
    public function transform(FacebookAccount $model): array
    {

        return [
            'id' => $model->id,
            'name' => $model->name,
            'channel_id' => $model->channel_id,
            'status' => $model->status,
            'action' => view('admin::module.facebook_account.datatables_actions', ['code' => $model->id])->render(),
        ];
    }
}
