<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\SubComment;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubCommentController extends BaseController
{
    protected $prefix;
    public function __construct()
    {
        $this->prefix = 'subcomment';
    }

    public function index($app, Request $request, $id)
    {
        try {
            $request = request()->all();
            $request['app'] = $app;            

            $validator = Validator::make($request, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,picircle,pispace'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $message = "Sub Komentar $app $id";
            $prefix = $this->prefix . '_list_' . $app . '_' . $id;
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            Comment::findOrFail($id);
            $select = [
                'subcomment.id',
                'sub_comment',
                DB::raw('reply_name.profile_picture'),
                DB::raw("CONCAT(reply_name.first_name, ' ', reply_name.last_name) AS reply_name"),
                'reply_name.id as reply_id',
                DB::raw("CONCAT(refer_name.first_name, ' ', refer_name.last_name) AS refer_name"),
                'refer_name.id as refer_id',
                'subcomment.action',
                'subcomment.sub_comment_id',
                DB::raw('coalesce(CAST(COUNT(subcomment_like.id) AS INT),0) AS like'),
                'subcomment.created_at',
                'reply_name.gender AS reply_gender',
            ];

            $comment = SubComment::select($select)
                ->join('users as  reply_name', 'subcomment.user_id', '=', 'reply_name.id')
                ->leftjoin('users  as refer_name', 'subcomment.refer_id', '=', 'refer_name.id')
                ->leftjoin('subcomment_like', 'subcomment.id', '=', 'subcomment_like.subcomment_id')
                ->where('subcomment.comment_id', $id)
                ->where('subcomment.status', 'publish')
                ->orderBy('subcomment.created_at', 'DESC');

            $groupBy = [
                'subcomment.id',
                'reply_name.username',
                'refer_name.username',
                'refer_name.id',
                'reply_name.id',
                'reply_name.profile_picture',
                'reply_name.id'
            ];

            $comment = $comment->groupBy($groupBy);
            $comment = $comment->get()->toArray();

            foreach ($comment as $key => $value) {
                $comment[$key]['profile_picture'] = User::getProfilePict($value['profile_picture'], $value['reply_gender']);
                unset($comment[$key]['reply_gender']);
                $comment[$key]['time'] = Helper::getTime($value['created_at']);
            }

            // Helper::setRedis($prefix, json_encode($comment), 500);
            return $this->sendResponse(result: $comment, message: $message);

        } catch (ModelNotFoundException $e) {
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function store($app, Request $request, $id)
    {
        try {
            $requestForm = request()->all();
            $requestForm['app'] = $app;

            $validator = Validator::make($requestForm, [
                'sub_comment' => 'required',
                'sub_comment_id' => 'nullable|integer',
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,picircle,pispace'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $user = $request->get('session_user');
            $user_id = $user['id'];
            $findComment = Comment::findOrFail($id);
            $sub_comment_id = $request->get('sub_comment_id');
            $refer_id = $findComment->user_id;
            if ($sub_comment_id) {
                $findSubComment = SubComment::findOrFail($sub_comment_id);
                $refer_id = $findSubComment->user_id;
            }

            $app = $request->get('app');
            $message = "Buat Sub Komentar $app $id";
            $prefix = $this->prefix . '_list_' . $app . '_' . $id;
            $subcomment = new SubComment();
            Helper::delRedis($prefix);

            $subcomment->sub_comment = strip_tags($request->input('sub_comment'));
            $subcomment->refer_id = $refer_id;
            if ($sub_comment_id) {
                $subcomment->action = 'reply-sub-comment';
                $subcomment->sub_comment_id = $sub_comment_id;
            }else{
                $subcomment->action = 'reply-comment';
            }

            $subcomment->user_id = $user_id;
            $subcomment->comment_id = $id;
            $subcomment->status = 'publish';
            $subcomment->save();
            return $this->sendResponse(result: $subcomment, message: $message);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function update($app, Request $request, $id)
    {
        try {
            $requestForm = request()->all();
            $requestForm['app'] = $app;
            
            $validator = Validator::make($requestForm, [
                'sub_comment' => 'required',
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,picircle,pispace'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }
            $app = $request->get('app');
            $user = $request->get('session_user');
            $user_id = $user['id'];
            $subcomment = SubComment::findOrFail($id);
            $author_id = $subcomment->user_id;
            if ($author_id != $user_id) {
                return $this->sendError(code: 401, error: 'Unauthorized');
            }

            $subcomment->sub_comment = strip_tags($request->input('sub_comment'));
            $subcomment->save();

            $message = "Perbarui Sub Komentar $app $id";
            $prefix = $this->prefix . '_list_' . $app . '_' . $subcomment->comment_id;
            Helper::delRedis($prefix);
            return $this->sendResponse(result: [], message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Sub Komentar Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function destroy($app, Request $request, $id)
    {
        try {
            $requestForm = request()->all();
            $requestForm['app'] = $app;
            
            $validator = Validator::make($requestForm, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,picircle,pispace'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $app = $request->get('app');
            $user = $request->get('session_user');
            $user_id = $user['id'];

            $subcomment = SubComment::findOrFail($id);
            $author_id = $subcomment->user_id;
            if ($author_id != $user_id) {
                return $this->sendError(code: 401, error: 'Unauthorized');
            }

            SubComment::where('sub_comment_id', $id)->delete();
            $subcomment->delete();

            $prefix = $this->prefix . '_list_' . $app . '_' . $subcomment->comment_id;
            Helper::delRedis($prefix);
            return $this->sendResponse(result: [], message: "Hapus subkomentar $app berdasarkan $id dengan sukses");

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Sub Komentar Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }
}
