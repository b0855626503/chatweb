<?php

namespace Gametech\Admin\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;


class BroadcastController extends AppBaseController
{
    protected $_config;

    public function __construct()
    {
        $this->_config = request('_config');

        $this->middleware('admin');
    }


    public function authenticate(Request $request)
    {
        return Broadcast::auth($request);
    }



}
