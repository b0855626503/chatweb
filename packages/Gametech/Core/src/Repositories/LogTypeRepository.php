<?php

namespace Gametech\Core\Repositories;

use Gametech\Core\Eloquent\Repository;

class LogTypeRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'GGametech\Core\Contracts\LogType';
    }
}
