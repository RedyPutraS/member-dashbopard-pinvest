<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\LikeShare;
use App\Models\DetailEvent;
use App\Models\OnlineCourse;
use Illuminate\Http\Request;
use App\Models\Article;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LikeShareController extends BaseController
{
    protected $type;
    protected $feature;
    protected $app_name;
    protected $prefix;

    public function __construct(Request $request)
    {
        $modul = ['event', 'online-course','article'];
        $this->type = $request->input('type');
        $this->feature = $request->input('feature');
        $this->app_name = ['pievent', 'pilearning', 'picircle'];
        if (!$this->type && !in_array($this->type, $modul)) {
            header('HTTP/1.0 400 Bad Request');
            die();
        }

        $this->prefix = 'like_share';
    }

    public function index($app, int $id)
    {
        try {
            $type = $this->type;
            if (!in_array($app, $this->app_name)) {
                abort(404);
            }

            $message = "Hitung Suka & Bagikan $type $app $id";
            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
            } elseif ($type === 'article') {
                Article::findOrFail($id);
            } else {
                DetailEvent::findOrFail($id);
            }

            // $prefix = $this->prefix . '_' . $app . '_' . $type . '_' . $id;
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            if ($type === 'online-course') {
                $sumlike = LikeShare::select('id')->where('online_course_id', $id)->where('feature','like')->count('id');
                $sumShare = LikeShare::select('id')->where('online_course_id', $id)->where('feature','share')->count('id');

            } elseif ($type === 'article') {
                $sumlike = LikeShare::select('id')->where('article_id', $id)->where('feature','like')->count('id');
                $sumShare = LikeShare::select('id')->where('article_id', $id)->where('feature','share')->count('id');
            } else {
                $sumlike = LikeShare::select('id')->where('detail_event_id', $id)->where('feature','like')->count('id');
                $sumShare = LikeShare::select('id')->where('detail_event_id', $id)->where('feature','share')->count('id');
            }

            $data = [
                'like' => $sumlike,
                'share' => $sumShare,
                'type'  => $type,
                'app' => $app
            ];

            // Helper::setRedis($prefix, json_encode($data), 500);
            return $this->sendResponse(result: $data, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function store($app, int $id, Request $request)
    {
        try {
            $type = $this->type;
            $feature = $request->input('feature');
            if (!in_array($app, $this->app_name)) {
                abort(404);
            }

            $user = $request->get('session_user');
            $user_id = $user['id'];

            $findLikeShare = new LikeShare();
            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
                $findLikeShare = $findLikeShare->select('id')
                    ->where('online_course_id', $id)
                    ->where('feature',$feature)
                    ->where('user_id', $user_id)->count();
            } elseif ($type === 'article') {
                Article::findOrFail($id);
                $findLikeShare = $findLikeShare->select('id')
                    ->where('article_id', $id)
                    ->where('feature',$feature)
                    ->where('user_id', $user_id)->count();
            } else {
                DetailEvent::findOrFail($id);
                $findLikeShare = $findLikeShare->select('id')
                    ->where('detail_event_id', $id)
                    ->where('feature',$feature)
                    ->where('user_id', $user_id)->count();
            }
            
            $message = "Buat Suka & Bagikan $app $id";
            // if($findLikeShare){
            //     $findLikeShare = new LikeShare();
            //     $findLikeShare = $findLikeShare
            //         ->where('article_id', $id)
            //         ->where('feature',$feature)
            //         ->where('user_id', $user_id)->first();
            //     dd($findLikeShare, $id);
            //     return $this->sendResponse(result: [], message: $message.' exist', status: 'deleted');
            // }

            if ($findLikeShare) {
                $findLikeShare = LikeShare::where('article_id', $id)
                    ->where('feature', $feature)
                    ->where('user_id', $user_id)
                    ->first();
                
                // Jika data ditemukan, hapus data tersebut
                if ($findLikeShare) {
                    $findLikeShare->delete(); // Hapus data
                    return $this->sendResponse(result: [], message: $message.' exist', status: 'deleted');
                }
            }


            $likeShare = new LikeShare();
            if ($type === 'online-course') {
                $likeShare->online_course_id = $id;
            } elseif ($type === 'article') {
                $likeShare->article_id = $id;
            } else {
                $likeShare->detail_event_id = $id;
            }

            $likeShare->user_id = $user_id;
            $likeShare->feature = $feature;
            $likeShare->save();
            return $this->sendResponse(result: [], message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function detail($app, int $id, Request $request)
    {
        try {
            $type = $this->type;
            if (!in_array($app, $this->app_name)) {
                abort(404);
            }

            $user = $request->get('session_user');
            $user_id = $user['id'];

            $message = "Hitung Suka & Bagikan $type $app $id";
            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
            } elseif ($type === 'article') {
                Article::findOrFail($id);
            } else {
                DetailEvent::findOrFail($id);
            }

            if ($type === 'online-course') {
                $sumlike = LikeShare::select('id')
                    ->where('online_course_id', $id)
                    ->where('user_id', $user_id)
                    ->where('feature','like')->count('id');
                $sumShare = LikeShare::select('id')
                    ->where('online_course_id', $id)
                    ->where('user_id', $user_id)
                    ->where('feature','share')->count('id');

            } elseif ($type === 'article') {
                $sumlike = LikeShare::select('id')
                    ->where('article_id', $id)
                    ->where('user_id', $user_id)
                    ->where('feature','like')->count('id');
                $sumShare = LikeShare::select('id')
                    ->where('article_id', $id)
                    ->where('user_id', $user_id)
                    ->where('feature','share')->count('id');

            } else {
                $sumlike = LikeShare::select('id')
                    ->where('detail_event_id', $id)
                    ->where('user_id', $user_id)
                    ->where('feature','like')->count('id');
                $sumShare = LikeShare::select('id')
                    ->where('detail_event_id', $id)
                    ->where('user_id', $user_id)
                    ->where('feature','share')->count('id');
            }

            $data = [
                'like' => $sumlike,
                'share' => $sumShare,
                'type'  => $type,
                'app' => $app
            ];

            return $this->sendResponse(result: $data, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }
}
