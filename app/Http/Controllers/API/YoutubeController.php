<?php

namespace App\Http\Controllers\API;

//use App\Models\Spotify;

use App\Library\Helper;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class YoutubeController extends BaseController
{
    protected $key;
    protected $channelId;
    protected $prefix;

    public function __construct()
    {
        $this->key = env('YOUTUBE_APP_KEY',null);
        $this->channelId = env('YOUTUBE_CHANNEL_ID',null);
        $this->prefix = 'picast_youtube';
    }

    private function orderItemByPublishAt($data, $type) {
        $publishedAtArr = [];
        foreach($data as $value) {
            $publishedAtArr[] = strtotime($value['snippet']['publishedAt']);
        }

        if($type == 'asc') {
            asort($publishedAtArr);
        } else {
            arsort($publishedAtArr);
        }

        $keysSorted = array_keys($publishedAtArr);
        $result = [];
        foreach($keysSorted as $key) {
            $result[] = $data[$key];
        }

        return $result;
    }

    public function playlist(){
        $url = "https://www.googleapis.com/youtube/v3/playlists?key=$this->key&channelId=$this->channelId&part=snippet,id&order=date&maxResults=20";
        $response = Http::get($url);
        $result = json_decode($response->body(),true);
        if(isset($result['error'])) {
            $errMessage = $result['error']['message'];
            return $this->sendError('Gagal mengambil data, ' . $errMessage, code: 500);
        }

        return $this->sendResponse(result: $result, message: 'success');
    }
    
    public function detail(Request $request, $id, $returnArray = false){
        $message = 'Detail Videonya';
        $prefix = 'playlist_youtube_tracks_'.$id;
        if($returnArray) {
            $prefix .= '_array';
        }

        $expire = 43200;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        ReferralController::store($request,'youtube',$id);
        $url = "https://www.googleapis.com/youtube/v3/videos?key=$this->key&part=snippet,statistics,contentDetails&id=$id";
        $response = Http::get($url);
        $result = json_decode($response->body(),true);
        if(isset($result['error'])) {
            $errMessage = $result['error']['message'];
            return $this->sendError('Gagal mengambil data, ' . $errMessage, code: 500);
        }

        if($returnArray) {
            // Helper::setRedis($prefix, $result, $expire);
            return isset($result['error']) ? [] : $result;
        }

        // Helper::setRedis($prefix, json_encode($result), $expire);
        return $result;
    }

    public function playlistItem($id, Request $request){
        $pageToken= $request->get('page-token');
        $prefix = $this->prefix.'_playlist';
        $limit= $request->get('limit')?? 20;
        if($pageToken){
            $prefix .= '_page-token_'.$pageToken;
        }

        $prefix .= '_limit_'.$limit;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis,true) : false;
        // if($getRedis){
        //     return $this->sendResponse(result: $getRedis,message: 'success');
        // }

        $url = "https://www.googleapis.com/youtube/v3/playlistItems?key=$this->key&playlistId={$id}&part=snippet,id,contentDetails&order=date&maxResults=$limit";
        if($pageToken){
            $url .= "&pageToken=$pageToken";
        }
        
        $response = Http::get($url);
        $result = json_decode($response->body(),true);
        if(isset($result['error'])) {
            $errMessage = $result['error']['message'];
            return $this->sendError('Gagal mengambil data, ' . $errMessage, code: 500);
        }

        $videoIds = array_column(array_column($result['items'], 'contentDetails'), 'videoId');
        $urlVideoData = "https://www.googleapis.com/youtube/v3/videos?key=$this->key&channelId=$this->channelId&part=statistics,contentDetails&id=" . implode(',', $videoIds);
        $responseVideoData = Http::get($urlVideoData);
        $resultVideoData = json_decode($responseVideoData->body(),true);
        
        foreach($resultVideoData['items'] as $item) {
            $index = array_search($item['id'], $videoIds);
            $result['items'][ $index ]['videoData'] = $item;
        }

        return $this->sendResponse(result: $result, message: 'success');

    }

    public function listVideoByChannelId(Request $request) : object
    {
        $prefix = $this->prefix;
        $message = 'Daftar Video Youtube berdasarkan ID Saluran';
        $videoType= $request->get('type');
        $limit= $request->get('limit')?? 20;
        $pageToken= $request->get('pageToken');
        $search= $request->get('search');
        $category= $request->get('category');

        $url = "https://www.googleapis.com/youtube/v3/search?key=$this->key&channelId=$this->channelId&part=snippet,id&type=video&maxResults=$limit";
        if(!empty($videoType)) {
            switch($videoType) {
                case 'popular':
                    $orderBy = 'viewCount';
                    break;
                case 'new':
                    $orderBy = 'date';
                    break;
                case 'recommendation':
                    $orderBy = 'rating';
                    break;
                case 'title-asc':
                case 'title-desc':
                    $orderBy = 'title';
                    break;

                default:
                    return $this->sendError(error: 'Jenis pencarian yang Anda cari tidak valid', code: '400');
            }
        }

        if(isset($orderBy)) {
            $url .= "&order=$orderBy";
            $prefix .= '_type_'.$orderBy;
        }

        if(!empty($category)) {
            $url .= "&videoCategoryId=$category";
            $prefix .= '_category_'.$category;
        }

        if(!empty($search)){
            $prefix .= '_search_'.$search;
            $url .= "&q=$search";
        }

        if(!empty($pageToken)){
            $prefix .= '_page-token_'.$pageToken;
            $url .= "&pageToken=$pageToken";
        }

        $prefix .= '_limit_'.$limit;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis,true) : false;
        // if($getRedis){
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $response = Http::get($url);
        $result = json_decode($response->body(),true);
        if(isset($result['error'])) {
            $errMessage = $result['error']['message'];
            return $this->sendError('Gagal mengambil data, ' . $errMessage, code: 500);
        }

        if($videoType == 'title-desc') {
            $result['items'] = collect($result['items'])->reverse()->all();
        } 

        $listVideoId = [];
        foreach($result['items'] as $i => $item) {
            if($item['id']['kind'] == 'youtube#video') {
                $listVideoId[ $item['id']['videoId'] ] = $i;
            }
        }

        if(count($listVideoId) > 0) {
            $urlVideoData = "https://www.googleapis.com/youtube/v3/videos?key=$this->key&channelId=$this->channelId&part=statistics,contentDetails,snippet&id=" . implode(',', array_keys($listVideoId));
            $responseVideoData = Http::get($urlVideoData);
            $resultVideoData = json_decode($responseVideoData->body(),true);

            foreach($resultVideoData['items'] as $item) {
                $index = $listVideoId[ $item['id'] ];
                $result['items'][ $index ]['videoData'] = $item;
            }
        }

        $expire = 3600;
        // Helper::setRedis($prefix,json_encode($result),$expire);
        return $this->sendResponse(result: $result, message: $message);
    }

    public function listCategory() : object
    {
        $message = 'Daftar Kategori Video';
        $prefix = $this->prefix . '_video_categories';

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis,true) : false;
        // if($getRedis){
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $url = "https://www.googleapis.com/youtube/v3/videoCategories?key=$this->key&regionCode=ID&hl=id";
        $response = Http::get($url);
        $result = json_decode($response->body(),true);
        if(isset($result['error'])) {
            $errMessage = $result['error']['message'];
            return $this->sendError('Gagal mengambil data, ' . $errMessage, code: 500);
        }

        $expire = 3600;
        // Helper::setRedis($prefix,json_encode($result),$expire);
        return $this->sendResponse(result: $result, message: $message);
    }
}
