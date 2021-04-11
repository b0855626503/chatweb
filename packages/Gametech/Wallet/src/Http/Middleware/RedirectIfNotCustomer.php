<?php

namespace Gametech\Wallet\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfNotCustomer
{
    /**
    * Handle an incoming request.
    *
    * @param  Request  $request
    * @param Closure $next
    * @param  string|null  $guard
    * @return mixed
    */
    public function handle($request, Closure $next, $guard = 'customer')
    {
        if (! Auth::guard($guard)->check()) {
            return redirect()->route('customer.session.index');
        } else {
            if (Auth::guard($guard)->user()->enable != 'Y') {
                Auth::guard($guard)->logout();

                session()->flash('warning', 'สมาชิกถูกระงับการใช้งาน โปรดติดต่อทีมงาน');

                return redirect()->route('customer.session.index');
            }
        }

        return $next($request);
    }
}
