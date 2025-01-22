<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\MasterBenefit;
use Illuminate\Http\Request;

class MasterBenefitController extends BaseController
{
    protected $prefix;
    protected $types;

    public function __construct()
    {
        $this->prefix = 'master_benefit';
        $this->types = [ 'partnership', 'membership' ];
    }

    public function index(Request $request)
    {
        $type = $request->get('type');
        if( empty($type) || !in_array($type, $this->types) ) {
            return $this->sendError('Tipe Tidak Valid.', code: 400);
        }

        $prefix = $this->prefix . '_' . $type;
        $message = 'Daftar Manfaat';

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $allBenefit = MasterBenefit::select('id', 'title', 'image', 'description')
            ->where('type', $type)
            ->get()->toArray();
        
        // Helper::setRedis($prefix, json_encode($allBenefit), 500);
        return $this->sendResponse(result: $allBenefit, message: $message);
    }
}
