<?php

namespace App\Http\Controllers\API;
use App\Models\PicastApp;

class PicastappController extends BaseController
{
    public function __construct()
    {
    }

    public function createOrUpdate($type,$id,$titleApp)
    {

        if($type==='youtube'){
            $match = ['youtube_id' => $id];
        }else if($type==='spotify'){
            $match = ['spotify_id' => $id];
        }

        $find = PicastApp::firstOrNew($match);
        $find->title = strip_tags($titleApp);
        $find->save();
        return $find;
    }
    public function destroy($app, int $id){
        $request = $this->request;
        $comment = Comment::find($id);
        $user_id = $comment->user_id;
        if($user_id!=$request->input('user_id')){
            return $this->sendError(code: 401,error:'Unauthorized');
        }
        $comment->delete();
        return $this->sendResponse(result: [], message: "Hapus komentar $app berdasarkan $id dengan sukses");
    }
}
