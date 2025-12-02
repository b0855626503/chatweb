<?php

namespace Gametech\FacebookOA\Repositories;

use Gametech\Core\Eloquent\Repository;

class FacebookTemplateRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return \Gametech\FacebookOA\Models\FacebookTemplate::class;

    }
}
