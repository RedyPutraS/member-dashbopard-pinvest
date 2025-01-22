<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Library\Helper;
use App\Models\MembershipGallery;

class MembershipGalleryController extends BaseController
{
    protected $prefix;
    public function __construct()
    {
        $this->prefix = 'membership_gallery';

    }
    public function gallery($id)
    {
        $prefix = $this->prefix.'_gallery_member'.$id;
        $message = "Daftar Galeri Paket Keanggotaan";

        $allGallery = MembershipGallery::select(['id', 'image'])
            ->where('status', 'publish')
            ->where('membership_id', $id)
            ->orderBy('order_number', 'asc')
            ->get()->toArray();
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        // Helper::setRedis($prefix, json_encode($allGallery), 500);
        return $this->sendResponse(result: $allGallery, message: $message);
    }
}
