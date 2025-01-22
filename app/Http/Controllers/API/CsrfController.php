<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Helper;

class CsrfController extends BaseController
{

    public function generate()
    {
        $generateToken = Helper::csrf();
        $secure = env('SESSION_SECURE_COOKIE',false);

        $output = ['status' => 'success', 'message' => 'Sukses mendapatkan Token Csrf'];
        return response()->json($output, 200)->withCookie(
            'XSRF-TOKEN', $generateToken['csrf_token'], 60, '/', config('session')['domain'], $secure, false
        );
    }
}
