<?php

namespace App\Http\Controllers\API;

use App\Models\Event;
use App\Models\OnlineCourse;
use App\Models\CommentLike;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\Request;
use Helper;
use Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CommentLikeController extends BaseController
{
    public function __construct(Request $request)
    {
        $this->type = $request->input('type');
        $this->request = $request;

    }

    public function store($app, $id, Request $request)
    {
        try {
            $requestValidator['app'] = $app;
            $requestValidator['id'] = $id;
            
            $validator = Validator::make($requestValidator, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace',
                'id' => 'required|integer'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }
            
            $app = $request->get('app');
            $user = $request->get('session_user');
            $user_id = $user['id'];

            Comment::findOrFail($id);
            $message = "Buat Komentar Suka $app $id";

            $match = ['user_id' => $user_id, 'comment_id' => $id];
            $commentLike = CommentLike::firstOrNew($match);
            $commentLike->save();
            return $this->sendResponse(result: $commentLike, message: $message);

        } catch (ModelNotFoundException $e) {
            return $this->sendError(error: 'Komentar Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function destroy($app, Request $request,$id){
        try {
            $requestValidator['app'] = $app;
            $requestValidator['id'] = $id;
            
            $validator = Validator::make($requestValidator, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace',
                'id' => 'required|integer'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            Comment::findOrFail($id);
            $user = $request->get('session_user');
            $user_id = $user['id'];

            $commentLike = CommentLike::where('comment_id', $id)
                ->where('user_id', $user_id)->first();
            if(!$commentLike) {
                return $this->sendError(error: 'Komentar Suka Tidak Ditemukan', code: 404);
            }
                
            if($commentLike->forceDelete()){
                return $this->sendResponse(result: [], message: 'success');
            }

            return $this->sendError(error: 'Failed', code: 500);

        } catch (ModelNotFoundException $e) {
            return $this->sendError(error: 'Komentar Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }
}
