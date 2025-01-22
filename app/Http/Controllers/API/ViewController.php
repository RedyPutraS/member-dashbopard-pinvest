<?php

namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

select subcomment.id, sub_comment, reply_name.profile_picture, subcomment.created_at, reply_name.username as reply_name, reply_name.id as reply_id, refer_name.username as refer_name, refer_name.id as refer_id, subcomment.action, coalesce(CAST(COUNT(subcomment_like.id) AS INT),0) AS like from subcomment inner join users as reply_name on subcomment.user_id = reply_name.id left join users as refer_name on subcomment.refer_id = refer_name.id left join subcomment_like on subcomment.id = subcomment_like.subcomment_id where subcomment.comment_id = 19 and subcomment.status = publish and subcomment.deleted_at is null group by subcomment.id, users.username, users.profile_picture, users.id order by subcomment.id asc

class ViewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
