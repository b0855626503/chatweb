<?php

namespace Gametech\Payment\Repositories;

use Gametech\Core\Eloquent\Repository;


class BankAccountRepository extends Repository
{
    public function getAccount($bank)
    {

        return $this
            ->in()->active()
            ->where('status_auto', 'Y')
            ->with('bank')
            ->whereHas('bank', function ($model) use ($bank) {
                $model->where('shortcode', strtoupper($bank));
            })->get();

    }

    public function getAccountInWeb()
    {

        return $this->in()->active()->topup()->show()->with('bank')->whereHas('bank')->get();

    }

    public function getAccountInAll()
    {

        return $this->in()->active()->with('bank')->whereHas('bank')->get();

    }

    public function getAccountInAlls()
    {

        return $this->in()->with('bank')->whereHas('bank')->get();

    }

    public function getAccountOutAll()
    {
        return $this->out()->active()->with('bank')->whereHas('bank')->get();

    }

    public function getAccountOutAlls()
    {
        return $this->out()->with('bank')->whereHas('bank')->get();

    }

    public function getAccountOne($bank, $account)
    {

        return $this
            ->in()->active()
            ->where('status_auto', 'Y')
            ->where('acc_no', $account)
            ->with('bank')
            ->whereHas('bank', function ($model) use ($bank) {
                $model->where('shortcode', strtoupper($bank));
            })->first();

    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Payment\Contracts\BankAccount';
    }
}
