<?php

namespace  Gametech\Wallet\Transformers;

use App\Home;
use League\Fractal\TransformerAbstract;

class HomeTransformer extends TransformerAbstract
{
    /**
     * @param Home $home
     * @return array
     */
    public function transform(Home $home)
    {
        return [
            'id' => (int) $home->id,
        ];
    }
}
