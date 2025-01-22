<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\Ads;
use App\Models\BannerPopupItem;
use App\Models\BannerPopup;
use App\Models\MappingApp;
use Illuminate\Http\Request;

class BannerPopupController extends BaseController
{
    protected $prefix;

    public function __construct()
    {
        $this->prefix = 'banner_popup';
    }

    public function index()
    {
        $message = 'Daftar Banner Popup';
        // $checkRedis = Helper::getRedis($this->prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $allBanner = BannerPopup::select(['id', 'banner_name', 'status'])
            ->where('status', 'publish')
            ->where('start_date', '<=', date('Y-m-d'))
            ->where('end_date', '>=', date('Y-m-d'))
            ->get()->toArray();

        $allBanner = array_map( function($banner) {
            $banner['app'] = MappingApp::select(['master_app_id', 'master_app.app_name'])
                ->join('master_app', 'master_app.id', 'mapping_app.master_app_id')
                ->where('mapping_app.banner_popup_id', $banner['id'])
                ->orderBy('mapping_app.id', 'ASC')
                ->get()->toArray();

            $banner['items'] = BannerPopupItem::select(['id', 'image', 'ads_url'])
                ->where('banner_popup_item.banner_popup_id', $banner['id'])
                ->orderBy('priority', 'DESC')
                ->get()->toArray();

            return $banner;
        }, $allBanner);

        // Helper::setRedis($this->prefix, json_encode($allBanner), 60);
        return $this->sendResponse($allBanner, $message);
    }

    public function byApp($app)
    {
        $message = 'Daftar Banner popup ' . $app;
        // $prefix = $this->prefix . '_' . $app;

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $allBanner = BannerPopupItem::select(['banner_popup_item.id', 'image', 'ads_url'])
            ->join('banner_popup', 'banner_popup.id', 'banner_popup_item.banner_popup_id')
            ->join('mapping_app', 'mapping_app.banner_popup_id', 'banner_popup.id')
            ->join('master_app', 'master_app.id', 'mapping_app.master_app_id')
            ->where('banner_popup.status', 'publish')
            ->where('master_app.alias', $app)
            ->where('banner_popup.start_date', '<=', date('Y-m-d'))
            ->where('banner_popup.end_date', '>=', date('Y-m-d'))
            ->orderBy('banner_popup_item.priority', 'ASC')
            ->get()->toArray();

        // Helper::setRedis($prefix, json_encode($allBanner), 60);
        return $this->sendResponse($allBanner, $message);
    }
}
