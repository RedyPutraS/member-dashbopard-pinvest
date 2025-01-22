<?php

namespace App\Http\Middleware;

use App\Library\Helper;
use Closure;
use Illuminate\Http\Request;

class EnsureCsrfIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        $token = $request->cookie('XSRF-TOKEN');
        // $verifyToken = Helper::verify_csrf($token);
        // if(!$verifyToken){
        //     return response()->json(['status' => 'failed', 'message' => 'csrf token expired'], 419);
        // }

        return $next($request);
    }
}
