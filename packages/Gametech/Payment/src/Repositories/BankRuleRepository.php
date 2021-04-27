<?php

namespace Gametech\Payment\Repositories;

use Gametech\Core\Eloquent\Repository;

class BankRuleRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Payment\Contracts\BankRule';
    }

    public function getRule()
    {
        return $this->with('bank');
    }
}
