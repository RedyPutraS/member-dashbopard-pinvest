<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\Newsletter;
use Illuminate\Http\Request;

class NewsletterController extends BaseController
{
    public function subscribe(Request $request)
    {
        $email = $request->input('email');
        // dd($email);
        $checkEmail = Newsletter::where('email', $email)->count() > 0;
        if($checkEmail) {
            return  $this->sendError('Email sudah digunakan', code: 400);
        }

        Newsletter::create([ 'email' => $email ]);
        return $this->sendResponse([], 'Berlangganan buletin dengan sukses.');
    }
}
