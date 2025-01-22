<?php

namespace App\Http\Controllers\API;

use App\Models\Rating;
use App\Models\Event;
use App\Models\OnlineCourse;
use Illuminate\Http\Request;
use App\Models\Article;
use App\Http\Controllers\API\YoutubeController;
use App\Http\Controllers\API\PicastappController;
use App\Http\Controllers\API\SpotifyController;
use App\Library\Helper;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RatingController extends BaseController
{
    protected $type;
    protected $prefix;

    public function __construct(Request $request)
    {
        $this->type = $request->input('type');
        $this->prefix = 'rating_event';
    }

    public function index($app, $id, Request $request)
    {
        try {
            $request = request()->all();
            $request['app'] = $app;

            $validator = Validator::make($request, [
                'type' => 'required|in:event,online-course,article,youtube,spotify',
                'app' => 'nullable|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $type = $this->type;
            $message = "Peringkat $app $id";

            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
            } elseif ($type === 'article') {
                Article::findOrFail($id);
            } elseif ($type === 'event') {
                Event::findOrFail($id);
            }
            
            // $prefix = $this->prefix . '_' . $app . '_' . $type . '_' . $id;
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ?? false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            if ($type === 'online-course') {
                $sumRating = Rating::select('rate')->where('online_course_id', $id)->sum('rate');
                $countRating = Rating::select('id')->where('online_course_id', $id)->count('id');
            } elseif ($type === 'article') {
                $sumRating = Rating::select('rate')->where('article_id', $id)->sum('rate');
                $countRating = Rating::select('id')->where('article_id', $id)->count('id');

            } elseif ($type === 'event') {
                $sumRating = Rating::select('rate')->where('event_id', $id)->sum('rate');
                $countRating = Rating::select('id')->where('event_id', $id)->count('id');
            } elseif ($type === 'youtube') {
                $sumRating = Rating::select('rate')->where('youtube_id', $id)->sum('rate');
                $countRating = Rating::select('id')->where('youtube_id', $id)->count('id');
            } elseif ($type === 'spotify') {
                $sumRating = Rating::select('rate')->where('spotify_id', $id)->sum('rate');
                $countRating = Rating::select('id')->where('spotify_id', $id)->count('id');
            } else {
                return $this->sendError(error: 'Permintaan yang buruk', code: 400);
            }
            
            $totalRate = round(($sumRating === 0 && $countRating === 0) ? 0 : $sumRating / $countRating, 1);
            $resultRating = ['total' => $totalRate, 'count' => $countRating];

            // Helper::setRedis($prefix, $resultRating, 500);
            return $this->sendResponse(result: $resultRating, message: $message);
        }catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);
        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal', code: 500);

        }
    }

    public function list($app, $id, Request $request)
    {
        try {
            $requestForm = request()->all();
            $requestForm['app'] = $app;
            
            $validator = Validator::make($requestForm, [
                'type' => 'required|in:event,online-course,article,youtube,spotify',
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace'

            ]);

            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $start = $request->get('start') ?? 0;
            $limit = $request->get('limit') ?? 10;
            if ($limit > 100) {
                $limit = 100;
            }

            $type = $this->type;
            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
            } elseif ($type === 'article') {
                Article::findOrFail($id);
            } elseif ($type === 'event') {
                Event::findOrFail($id);
            }

            $message = "Daftar Peringkat $app $id";
            // $prefix = $this->prefix . '_list' . '_' . $app . '_' . $start . '_' . $limit . '_' . $type . '_' . $id;
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $select = [
                'rating.id',
                'rating.user_name',
                'users.first_name as author',
                DB::raw('users.profile_picture'),
                'rating.rate',
                'rating.title',
                'rating.notes',
                'rating.created_at',
                'users.gender AS user_gender',
            ];
            if ($type === 'online-course') { 
                $list = Rating::select($select)->where('online_course_id', $id);
            } elseif ($type === 'article') {
                $list = Rating::select($select)->where('article_id', $id);
            } elseif ($type === 'event') {
                $list = Rating::select($select)->where('event_id', $id);
            } elseif ($type === 'youtube') {
                $list = Rating::select($select)->where('youtube_id', $id);
            } elseif ($type === 'spotify') {
                $list = Rating::select($select)->where('spotify_id', $id);
            } else {
                return $this->sendError(error: 'Permintaan yang buruk', code: 400);
            }

            $list = $list->join('users', 'rating.user_id', '=', 'users.id')
                ->orderBy('rating.created_at', 'DESC')->limit($limit)->offset($start)
                ->get()->toArray();
            foreach ($list as $key => $value) {
                $list[$key]['profile_picture'] = User::getProfilePict($value['profile_picture'], $value['user_gender']);
                unset($list[$key]['user_gender']);
                $list[$key]['time'] = Helper::getTime($value['created_at']);

                $list[$key]['author'] = !empty($value['user_name']) ? $value['user_name'] : $value['author'];
                unset($list[$key]['user_name']);
            }

            // Helper::setRedis($prefix, json_encode($list), 500);
            return $this->sendResponse(result: $list, message: $message);

        }catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);
        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function store($app, $id, Request $request)
    {
        try {
            $requestForm = request()->all();
            $requestForm['app'] = $app;
            
            $validator = Validator::make($requestForm, [
                'rate' => 'required|numeric|between:1,5.0',
                'type' => 'required|in:event,online-course,article,youtube,spotify',
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace'
            ]);
            
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $type = $this->type;
            $app = $request->get('app');
            $external = $request->get('external');
            $user = $request->get('session_user');
            $user_id = $user['id'];

            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
            } elseif ($type === 'article') {
                Article::findOrFail($id);
            } elseif ($type === 'event') {
                Event::findOrFail($id);

            } elseif ($type == 'youtube') {
                $getDetail = (new YoutubeController)->detail($request, $id, true);
                $titleApp = $getDetail['items'][0]['snippet']['title'] ?? null;
                if (!$titleApp) {
                    return $this->sendError(error: 'Permintaan id youtube tidak valid', code: 400);
                }

                $insertPicastApp = (new PicastappController)->createOrUpdate($type, $id, $titleApp);

            } elseif ($type == 'spotify') {
                $getDetail = (new SpotifyController)->detail($request, $id, true);
                $titleApp = $getDetail['name'] ?? null;
                if (!$titleApp) {
                    return $this->sendError(error: 'ID Spotify permintaan tidak valid', code: 400);
                }

                $insertPicastApp = (new PicastappController)->createOrUpdate($type, $id, $titleApp);
            }

            $message = "Buat atau Perbarui Peringkat $app $id";
            $listPrefix = $this->prefix . 'list' . '_' . $app . '_' . $type . '_' . $id;
            $prefix = $this->prefix . '_' . $app . '_' . $type . '_' . $id;
            Helper::delRedis($prefix);
            Helper::delRedis($listPrefix);

            $match = ['user_id' => $user_id];

            if ($type === 'online-course') {
                $match['online_course_id'] = $id;
            } elseif ($type === 'article') {
                $match['article_id'] = $id;
            } elseif ($type === 'event') {
                $match['event_id'] = $id;
            } elseif ($type == 'youtube') {
                $match['youtube_id'] = $id;
            } elseif ($type == 'spotify') {
                $match['spotify_id'] = $id;
            } else {
                return $this->sendError(error: 'Permintaan yang buruk', code: 400);
            }

            $rating = Rating::firstOrNew($match);
            $rating->title = strip_tags($request->input('title'));
            $rating->notes = strip_tags($request->input('notes'));

            if ($type === 'online-course') {
                $rating->online_course_id = $id;
            } elseif ($type === 'article') {
                $rating->article_id = $id;
            } elseif ($type === 'event') {
                $rating->event_id = $id;
            } elseif ($type == 'youtube') {
                $rating->youtube_id = $id;
            } elseif ($type == 'spotify') {
                $rating->spotify_id = $id;
            }

            $rating->rate = (int)$request->input('rate') > 5 ? 5 : $request->input('rate');
            $rating->save();
            return $this->sendResponse(result: $rating, message: $message);

        }catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);
        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);

        }
    }

    public function myRating($app, $id, Request $request)
    {
        try {
            $requestForm = request()->all();
            $requestForm['app'] = $app;
            
            $validator = Validator::make($requestForm, [
                'type' => 'required|in:event,online-course,article,youtube,spotify',
                'app' => 'nullable|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace'

            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $type = $this->type;
            $user = $request->get('session_user');
            $user_id = $user['id'];
            $message = "Peringkat Saya $app $id";
            
            $rating = Rating::where('user_id',$user_id);
            if ($type === 'online-course') {
                $rating = $rating->where('online_course_id',$id);
            } elseif ($type === 'article') {
                $rating = $rating->where('article_id',$id);
            } elseif ($type === 'event') {
                $rating = $rating->where('event_id',$id);
            } elseif ($type == 'youtube') {
                $rating = $rating->where('youtube_id',$id);
            } elseif ($type == 'spotify') {
                $rating = $rating->where('spotify_id',$id);
            }
            $rating = $rating->get()->toArray();
            return $this->sendResponse(result: $rating, message: $message);
        }catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);
        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal', code: 500);

        }


    }

}
