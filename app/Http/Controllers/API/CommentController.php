<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\Event;
use App\Models\OnlineCourse;
use App\Models\Comment;
use App\Models\SubComment;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use phpseclib3\Crypt\RC2;

class CommentController extends BaseController
{
    protected $prefix;
    public function __construct(Request $request)
    {
        $this->prefix = 'comment';
    }

    public function index($app, $id, Request $request)
    {
        try {
            $requestValidator = $request->all();
            $requestValidator['app'] = $app;
            $validator = Validator::make($requestValidator, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace',
                'type' => 'required|in:event,online-course,article,youtube,spotify',
            ]);

            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $type = $request->get('type');
            $start = $request->get('start') ?? 0;
            $limit = $request->get('limit') ?? 10;
            if ($limit > 100) {
                $limit = 100;
            }

            $message = "Daftar Komentar $app $type $id";
            if ($type === 'online-course') {
                OnlineCourse::findOrFail($id);
            } elseif ($type === 'article') {
                Article::findOrFail($id);
            } elseif ($type === 'event') {
                Event::findOrFail($id);
            }

            // $prefix = $this->prefix . '_' . $app . '_' . $start . '_' . $limit . '_' . $type . '_' . $id;
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $select = [
                'comment.id',
                'comment.user_name',
                'comment',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS author"),
                DB::raw('users.profile_picture'),
                'comment.created_at',
                'users.id as user_id',
                'comment.image as comment_image',
                Db::raw('coalesce(CAST(COUNT(comment_like.id) AS INT),0) AS like'),
                'users.gender AS user_gender',
            ];
            $subQuery = '(SELECT*FROM comment_like WHERE deleted_at IS NULL)comment_like';
            $comment = Comment::select($select)->leftJoin(DB::raw($subQuery), 'comment.id', '=', 'comment_like.comment_id');
            if ($type === 'online-course') {
                $comment = $comment->join('users', 'comment.user_id', '=', 'users.id')
                ->join('online_course', 'comment.online_course_id', '=', 'online_course.id')
                ->where('comment.online_course_id', $id)
                ->where('comment.status', 'publish');

            } elseif ($type === 'article') {
                $comment = $comment->join('users', 'comment.user_id', '=', 'users.id')
                ->join('article', 'comment.article_id', '=', 'article.id')
                ->where('comment.article_id', $id);

            } elseif ($type === 'event') {
                $comment = $comment->join('users', 'comment.user_id', '=', 'users.id')
                ->join('event', 'comment.event_id', '=', 'event.id')
                ->where('comment.event_id', $id);

            } elseif ($type === 'youtube') {
                $comment = $comment->join('users', 'comment.user_id', '=', 'users.id')
                ->where('comment.youtube_id', $id);

            } elseif ($type === 'spotify') {
                $comment = $comment->join('users', 'comment.user_id', '=', 'users.id')
                    ->where('comment.spotify_id', $id);
                }

                $groupBy = [
                    'comment.id',
                    'users.username',
                    'users.profile_picture',
                    'users.id',
                ];

                $comment = $comment->groupBy($groupBy);
                $comment =   $comment->orderBy('comment.created_at', 'DESC');
                $comment =   $comment->where('comment.status', 'publish')->get()->toArray();

                if ($comment) {
                    foreach ($comment as $key => $value) {
                        $comment[$key]['profile_picture'] = User::getProfilePict($value['profile_picture'], $value['user_gender']);
                        unset($comment[$key]['user_gender']);

                        $comment[$key]['author'] = !empty($value['user_name']) ? $value['user_name'] : $value['author'];
                        unset($comment[$key]['user_name']);

                        $comment[$key]['time'] = Helper::getTime($value['created_at']);
                        $comment[$key]['subcomment'] = SubComment::select('id')
                        ->where('comment_id', $value['id'])
                        ->where('subcomment.status', 'publish')
                        ->count();
                    }
                }

            // Helper::setRedis($prefix, json_encode($comment), 60);
            return $this->sendResponse(result: $comment, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }

    }


    public function store($app, $id, Request $request)
    {
        try {
            $requestValidator = $request->all();
            $requestValidator['app'] = $app;

            $validator = Validator::make($requestValidator, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace',
                'type' => 'required|in:event,online-course,article,youtube,spotify',
                'comment' => 'required',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:1000',
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $type = $request->get('type');
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

            $message = "Buat Komentar $app $id";
            $prefix = $this->prefix . '_' . $app . '_' . $type . '_' . $id;
            Helper::delRedis($prefix);
            $comment = new Comment();
            if ($type === 'online-course') {
                $comment->online_course_id = $id;
            } elseif ($type === 'article') {
                $comment->article_id = $id;
            } elseif ($type === 'event') {
                $comment->event_id = $id;
            } elseif ($type === 'youtube') {
                $comment->youtube_id = $id;
            } elseif ($type === 'spotify') {
                $comment->spotify_id = $id;
            }

            $image = $request->file('image');
            if ($image) {
                $uploadOss = new UploadController();
                $uploadOssThumbnail = $uploadOss->ossUpload(file: $image, prefix: "image_comment");
                $comment->image = $uploadOssThumbnail['url'];
            }

            $comment->comment = strip_tags($request->input('comment'));
            $comment->user_id = $user_id;
            $comment->status = 'publish';
            $comment->save();
            return $this->sendResponse(result: $comment, message: $message);

        }catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function update($app, $id, Request $request)
    {
        try {
            $requestValidator = $request->all();
            $requestValidator['app'] = $app;

            $validator = Validator::make($requestValidator, [
                'type' => 'required|in:event,online-course,article,youtube,spotify',
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace',
                'comment' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $message = "Memperbarui komentar $id berhasil";
            $user = $request->get('session_user');
            $user_id = $user['id'];
            $comment = Comment::findOrFail($id);

            if($comment['user_id']!==$user_id){
                return $this->sendError(code: 401, error: 'Unauthorized');
            }

            $comment->comment = strip_tags($request->input('comment'));
            $comment->save();
            return $this->sendResponse(result: $comment, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }


    public function destroy($app, $id, Request $request)
    {
        try {
            $requestValidator['app'] = $app;
            $validator = Validator::make($requestValidator, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace'
            ]);

            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $comment = Comment::find($id);
            if(!$comment) {
                return $this->sendError(error: 'Tidak Ditemukan', code: 404);
            }

            $user = $request->get('session_user');
            $user_id = $user['id'];
            if ($user_id != $comment['user_id']) {
                return $this->sendError(code: 401, error: 'Unauthorized');
            }

            $comment->delete();
            return $this->sendResponse(result: [], message: "Hapus komentar $app berdasarkan $id dengan sukses");

        }catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }
}
