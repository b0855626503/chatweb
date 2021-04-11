<?php

namespace Gametech\Admin\Http\Controllers;


use Illuminate\Support\Facades\Artisan;


class CmdController extends AppBaseController
{
    protected $_config;

    public function __construct()
    {
        $this->_config = request('_config');

        $this->middleware('admin');
    }


    public function storeLink()
    {
        Artisan::call('storage:link');
        return 'Store link';
    }

    public function optimizeClear()
    {
        Artisan::call('optimize:clear');
        return 'Optimize Clear';
    }

    public function optimize()
    {
        Artisan::call('optimize');
        return 'Optimize Cache';
    }

    public function webServiceStart()
    {
        Artisan::call('websockets:serve --host=127.0.0.1');
        return 'Websockets start';
    }

    public function webServiceStop()
    {
        Artisan::call('websockets:restart');
        return 'Websockets Restart';
    }

    public function viewCmd()
    {
        Artisan::call('view:clear');
        return 'View Clear';
    }

    public function cacheCmd()
    {
        Artisan::call('cache:clear');
        return 'Cache Clear';
    }


}
