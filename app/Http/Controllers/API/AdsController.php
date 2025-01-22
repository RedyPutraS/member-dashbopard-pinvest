<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\Ads;
use App\Models\AdsItem;
use App\Models\MappingApp;
use Illuminate\Http\Request;

class AdsController extends BaseController
{

    protected $prefix;

    public function __construct()
    {
        $this->prefix = 'ads';
    }

    public function index()
    {
        $message = 'Daftar Iklan';
        // $checkRedis = Helper::getRedis($this->prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $allAds = Ads::select(['id', 'ads_name', 'status'])
            ->where('status', 'publish')
            ->where('start_date', '<=', date('Y-m-d'))
            ->where('end_date', '>=', date('Y-m-d'))
            ->get()->toArray();

        $allAds = array_map( function($ads) {
            $ads['app'] = MappingApp::select(['master_app_id', 'master_app.app_name'])
                ->join('master_app', 'master_app.id', 'mapping_app.master_app_id')
                ->where('mapping_app.ads_id', $ads['id'])
                ->orderBy('master_app.app_name', 'ASC')
                ->get()->toArray();

            $ads['items'] = AdsItem::select(['id', 'image', 'type', 'url'])
                ->where('ads_item.ads_id', $ads['id'])
                ->orderBy('priority', 'DESC')
                ->get()->toArray();

            return $ads;
        }, $allAds);

        // Helper::setRedis($this->prefix, json_encode($allAds), 60);
        return $this->sendResponse($allAds, $message);
    }

    public function byApp($app)
    {
        $message = 'Daftar Iklan ' . $app;
        // $prefix = $this->prefix . '_' . $app;

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $allAds = AdsItem::select(['ads_item.id', 'image', 'type', 'url', 'ads.status'])
            ->join('ads', 'ads.id', 'ads_item.ads_id')
            ->join('mapping_app', 'mapping_app.ads_id', 'ads.id')
            ->join('master_app', 'master_app.id', 'mapping_app.master_app_id')
            ->where('master_app.alias', $app)
            ->where('ads.status', 'publish')
            ->where('ads.start_date', '<=', date('Y-m-d'))
            ->where('ads.end_date', '>=', date('Y-m-d'))
            ->orderBy('ads_item.priority', 'asc')
            ->get()->toArray();

        // Helper::setRedis($prefix, json_encode($allAds), 60);
        return $this->sendResponse($allAds, $message);
    }
}
