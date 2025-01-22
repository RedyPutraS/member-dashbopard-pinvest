<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Helper;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Space_package as SpacePackage;

class BannerController extends BaseController
{
    public function __construct()
    {
        $this->prefix = 'banner';
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab');
        $message = 'Daftar Spanduk ' . $tab;
        $prefix = 'list_' . $this->prefix . '_' . $tab;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }
        
        $select = [
            'banner.id',
            'banner.slider_image',
            'banner.title',
            'banner.description',
            'banner.url',
            'banner.order_number',
            'banner.type',
        ];
        $banner = Banner::select($select)->join('master_app', 'banner.master_app_id', '=', 'master_app.id');
        $banner = $banner->where('banner.status', '=', 'publish');
        $banner = $banner->where('master_app.alias', '=', $tab);
        $banner = $banner->orderBy('banner.order_number', 'ASC');
        $banner = $banner->get()->toArray();
        // dd($banner);
        // Helper::setRedis($prefix, json_encode($banner), 500);
        return $this->sendResponse(result: $banner, message: $message);
    }
}
