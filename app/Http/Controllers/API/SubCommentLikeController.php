<?php

namespace App\Http\Controllers\API;

use App\Models\Comment;
use App\Models\SubComment;
use App\Models\SubCommentLike;
use Illuminate\Http\Request;
use Helper;
use Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubCommentLikeController extends BaseController
{
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->prefix = 'subcomment_like';

    }

    public function store($app, $id)
    {
        try {
            $requestForm['app'] = $app;
            $requestForm['id'] = $id;
            $validator = Validator::make($requestForm, [
                'app' => 'required|in:pievent,pilearning,picast,picapital,pinews,pievent,picircle,pispace',
                'id' => 'required|integer'
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan buruk', errorMessages: $validator->errors(), code: 400);
            }

            $request = $this->request;
            $app = $request->get('app');
            $user = $request->get('session_user');
            $user_id = $user['id'];

            SubComment::findOrFail($id);
            $message = "Buat Sub Komentar Suka $app $id";

            $prefix = $this->prefix . '_' . $app . '_' . $id;
            Helper::delRedis($prefix);

            $match = ['user_id' => $user_id, 'subcomment_id' => $id];
            $subCommentLike = SubCommentLike::firstOrNew($match);
            $subCommentLike->save();
            return $this->sendResponse(result: $subCommentLike, message: $message);

        } catch (ModelNotFoundException $e) {
            return $this->sendError(error: 'Sub Komentar Tidak Ditemukan', code: 404);
            
        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function destroy($app, Request $request, $id){
        try {
            $user = $request->get('session_user');
            $user_id = $user['id'];
            SubComment::findOrFail($id);

            $subCommentLike = SubCommentLike::where('subcomment_id', $id)
                ->where('user_id', $user_id)->first();
            if(!$subCommentLike) {
                return $this->sendError(error: 'Komentar Suka Tidak Ditemukan', code: 404);
            }
                
            if($subCommentLike->forceDelete()){
                return $this->sendResponse(result: [], message: 'success');
            }

            return $this->sendError(error: 'Failed', code: 500);

        } catch (ModelNotFoundException $e) {
            return $this->sendError(error: 'Sub Komentar Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }
}
