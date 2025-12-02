<?php

namespace Gametech\FacebookOA\Facades;

use Illuminate\Support\Facades\Facade;

class UrlHelper extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'facebookoa.urlhelper';
    }
}
