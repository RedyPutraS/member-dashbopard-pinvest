<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Helper;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerController extends BaseController
{
    public function __construct()
    {
        $this->prefix = 'list_apps';
    }
    public function index(Request $request)
    {
        $prefix = $this->prefix;
        $message = 'Daftar Mitra';

        $allPartner = Partner::select(['id', 'name', 'image'])
            ->get()->toArray();

        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        if ($getRedis) {
            return $this->sendResponse(result: $getRedis, message: $message);
        }

        Helper::setRedis($prefix, json_encode($allPartner), 500);
        return $this->sendResponse(result: $allPartner, message: $message);
    }
}
