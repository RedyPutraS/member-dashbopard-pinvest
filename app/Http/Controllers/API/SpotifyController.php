<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\Spotify;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;


class SpotifyController extends BaseController
{
    protected $prefix;

    public function __construct()
    {
        $this->prefix = 'spotify_token';
    }

    public function token($returnToken = false)
    {
        try {
            $prefix = $this->prefix;
            $message = 'Token yang dihasilkan';
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
                'client_id' => env('CLIENT_ID_SPOTIFY', 'ae8227e064f54fa1961365d8a042f236'),
                'client_secret' => env('CLIENT_SECRET_SPOTIFY', '56650fc91770470aaf7af60368c2c630'),
                'grant_type' => 'client_credentials',
            ]);
            // dd($response);
            $result = json_decode($response->body(), true);
            $spotify = new Spotify;
            $token = $result['access_token'];
            $spotify->token = $token;
            $spotify->response = $response->body();
            $spotify->save();
            $expire = $result['expires_in'] - 100;
            $data['token'] = $result['access_token'];

            if($returnToken) return $result['access_token'];

            // Helper::setRedis($prefix, json_encode($data), $expire);
            return $this->sendResponse(result: $data, message: $message);

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function detail(Request $request, $id, $returnArray = false){
        try {
            $expire = 43200;
            $prefix = 'playlist_spotify_episode_'.$id;
            $message = 'Daftar Putar Episode Detail';

            if($returnArray) {
                $prefix .= '_array';
            }

            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            ReferralController::store($request,'spotify',$id);
            $token = self::token(true);
            $url = 'https://api.spotify.com/v1/episodes/'.$id.'?market=ID';
            $response = Http::withToken($token)->get($url);
            $result = json_decode($response->body(),true);

            if($returnArray) {
                // Helper::setRedis($prefix, $result, $expire);
                return isset($result['error']) ? [] : $result;
            }

            if(isset($result['error'])) {
                $errMessage = $result['error']['message'];
                return $this->sendError('Failed to fetch data, ' . $errMessage, code: 500);
            }

            // Helper::setRedis($prefix, json_encode($result), $expire);
            return $this->sendResponse(result: $result, message: $message);

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function episodes(Request $request)
    {
        try {
            $prefix = 'playlist_spotify';
            $message = 'Daftar Putar Spotify';

            $limit = $request->get('limit', 12);
            $limit = $limit > 100 ? 100 : $limit;
            $start = $request->get('start') ?? 0;
            $search = trim($request->get('search'));
            $sort = trim($request->get('sort'));
            $category = trim($request->get('category'));

            if (!empty($search)) {
                $prefix .= '_search_' . $search;
                $message = 'Daftar Putar Spotify Dengan Pencarian ' . $search;
            }

            if(!empty($sort)) {
                $prefix .= '_sort_' . $sort;
            }
            
            if(!empty($category)) {
                $prefix .= '_category_' . $category;
            }

            $prefix .= '_limit_' . $limit .'_start_' . $start;

            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $currentPage = (int) $request->get('page', 1);
            $start = ($currentPage > 1) ? ($currentPage * $limit) - $limit : 0;

            $token = self::token(true);
            $spotifyPlaylistId = env('SPOTIFY_PLAYLIST_ID', '7nYDx1YE3eAqvZKWXftd9m');
            $url = "https://api.spotify.com/v1/playlists/{$spotifyPlaylistId}?market=ID&offset={$start}&limit={$limit}";
            $response = Http::withToken($token)->get($url);
            $result = json_decode($response->body(),true);

            if(isset($result['error'])) {
                $errMessage = $result['error']['message'];
                return $this->sendError('Failed to fetch data, ' . $errMessage, code: 500);
            }

            $response = Http::withToken($token)->get($url);
            $result = json_decode($response->body(),true);
            $nextResponse = Http::withToken($token)->get($result['tracks']['next']);
            $resultNextResponse = json_decode($nextResponse->body(),true);
            $result['tracks']['items']= array_merge($result['tracks']['items'],$resultNextResponse['items']);

            if(!empty($search)) {
                $newItems = [];
                foreach ($result['tracks']['items'] as $key => $value) {
                    if (preg_match("/$search/i", $value['track']['name'])) {
                        $newItems[] = $value;
                    }
                }

                $result['tracks']['items'] = $newItems;
                $result['tracks']['total'] = count($newItems);
            }

            if(!empty($sort)) {
                switch($sort) {
                    case 'name-asc':
                    case 'name-desc':
                        $newItems = collect($result['tracks']['items']);
                        $newItemsTracks = $newItems->pluck('track');

                        if($sort == 'name-asc') {
                            $newItemsTracks = $newItemsTracks->sortBy( function($value) {
                                return trim($value['name']);
                            });
                        } else {
                            $newItemsTracks = $newItemsTracks->sortByDesc( function($value) {
                                return trim($value['name']);
                            });
                        }

                        $result['tracks']['items'] = [];
                        foreach($newItemsTracks as $iTrack => $itemTrack) {
                            $result['tracks']['items'][] = $newItems[ $iTrack ];
                        }
                        break;
                }
            }

            $paginator = Helper::createPaginator($result['tracks']['total'], $limit, $currentPage);
            $pagination = [
                'total_data' => $result['tracks']['total'],
                'total_page' => $paginator->getNumPages(),
                'current_page' => $currentPage,
                'prev_page_url' => $paginator->getPrevUrl(),
                'next_page_url' => $paginator->getNextUrl(),
                'links' => []
            ];
    
            foreach($paginator->getPages() as $page) {
		if($page['url']){
                	$pagination['links'][] = [
                    		'url' => $page['url'],
                    		'label' => (string) $page['num'],
                    		'active' => $page['isCurrent'],
                	];
		}
            }

            $result['tracks']['items'] = array_slice($result['tracks']['items'], $start, $limit);
            $expire = 3600;
            Helper::setRedis($prefix, json_encode($result), $expire);
            return $this->sendResponse(result: $result, message: $message, pagination: $pagination);

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function listCategory() : object
    {
        try {
            $message = 'Daftar Kategori Spotify';
            $prefix = $this->prefix . '_categories';

            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis,true) : false;
            // if($getRedis){
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $token = self::token(true);
            $url = "https://api.spotify.com/v1/browse/categories";
            $response = Http::withToken($token)->get($url);
            $result = json_decode($response->body(),true);

            if(isset($result['error'])) {
                $errMessage = $result['error']['message'];
                return $this->sendError('Failed to fetch data, ' . $errMessage, code: 500);
            }

            $result = collect($result);
            $result = $result->sortBy( function($value) {
                return trim($value['name']);
            });

            $expire = 3600;
            // Helper::setRedis($prefix,json_encode($result),$expire);
            return $this->sendResponse(result: $result, message: $message);

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function detailPlaylist()
    {
        try {
            $message = 'Dapatkan Detail Daftar Putar Spotify';
            $prefix = $this->prefix . '_detail_playlist';

            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis,true) : false;
            // if($getRedis){
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $showId = env('SPOTIFY_SHOW_ID');
            $token = self::token(true);

            $url = "https://api.spotify.com/v1/shows/{$showId}?market=ID";
            $response = Http::withToken($token)->get($url);
            $result = json_decode($response->body(),true);

            if(isset($result['error'])) {
                $errMessage = $result['error']['message'];
                return $this->sendError('Failed to fetch data, ' . $errMessage, code: 500);
            }

            unset($result['episodes']);
            $expire = 3600;
            // Helper::setRedis($prefix,json_encode($result),$expire);
            return $this->sendResponse(result: $result, message: $message);

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }
}
