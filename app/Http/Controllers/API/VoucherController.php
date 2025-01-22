<?php

namespace App\Http\Controllers\API;

use App\Models\Voucher;
use App\Models\DetailEvent;
use App\Models\OnlineCourse;
use Illuminate\Http\Request;
use Helper;
use App\Models\Article;

class VoucherController extends BaseController
{
    public function __construct(Request $request)
    {
        $modul = ['event','online-course','article'];
        $this->type = $request->input('type');
        $this->app_name = ['pievent', 'pilearning'];
        $this->request = $request;
        if(!$this->type && !in_array($this->type,$modul)){
            header('HTTP/1.0 400 Bad Request');
            die();
        }
        $this->prefix = 'rating_event';

    }

    public function index($app, int $id)
    {
        $type = $this->type;

        if (!in_array($app, $this->app_name)) {
            abort(404);
        }
        $message = "Voucher $app $id";
        if($type==='online-course'){
            OnlineCourse::findOrFail($id);
        }elseif ($type==='article'){
            Article::findOrFail($id);
        }else{
            DetailEvent::findOrFail($id);
        }
        $prefix = $this->prefix . '_' . $app .'_'.$type. '_' . $id;
        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ?? false;
        if ($getRedis) {
            return $this->sendResponse(result: $getRedis, message: $message);
        }
        if($type==='online-course'){
            $sumVoucher = Voucher::select('rate')->where('online_course_id', $id)->sum('rate');
            $countVoucher = Voucher::select('id')->where('online_course_id', $id)->count('id');
        }elseif($type==='article'){
            $sumVoucher = Voucher::select('rate')->where('detail_event_id', $id)->sum('rate');
            $countVoucher = Voucher::select('id')->where('detail_event_id', $id)->count('id');

        }else{
            $sumVoucher = Voucher::select('rate')->where('detail_event_id', $id)->sum('rate');
            $countVoucher = Voucher::select('id')->where('detail_event_id', $id)->count('id');
        }
        $totalRate = ($sumVoucher === 0 && $countVoucher === 0) ? 0 : $sumVoucher / $countVoucher;
        Helper::setRedis($prefix, $totalRate, 500);
        return $this->sendResponse(result: (string)$totalRate, message: $message);
    }

    public function list($app, int $id)
    {
        $type = $this->type;
        if (!in_array($app, $this->app_name)) {
            abort(404);
        }
        if($type==='online-course'){
            OnlineCourse::findOrFail($id);
        }elseif($type==='article'){
            Article::findOrFail($id);
        }else{
            DetailEvent::findOrFail($id);
        }
        $message = "Daftar Voucher $app $id";
        $prefix = $this->prefix .'_list'. '_' . $app .'_'.$type. '_' . $id;
        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ? json_decode($checkRedis) : false;
        if ($getRedis) {
            return $this->sendResponse(result: $getRedis, message: $message);
        }
        
        $select = [
            'rating.id',
            'users.first_name',
            'rating.rate',
            'rating.title',
            'rating.notes',
            'rating.created_at',
        ];
        if($type==='online-course'){
            $list = Voucher::select($select)->where('online_course_id', $id);
        }elseif($type==='article'){

            $list = Voucher::select($select)->where('online_course_id', $id);
        }else{
            $list = Voucher::select($select)->where('detail_event_id', $id);
        }

        $list = $list->join('users', 'rating.user_id','=', 'users.id')
            ->get()->toArray();
        Helper::setRedis($prefix, json_encode($list), 500);
        return $this->sendResponse(result: $list, message: $message);
    }
    public function store($app, int $id)
    {

        $type = $this->type;
        $request = $this->request;
        if (!in_array($app, $this->app_name)) {
            abort(404);
        }
        if($type==='online-course') {
            OnlineCourse::findOrFail($id);
        }elseif($type==='article'){
            Article::findOrFail($id);
        }else{
            DetailEvent::findOrFail($id);
        }
        $message = "Buat Voucher $app $id";
        $listPrefix = $this->prefix .'list'. '_' . $app .'_'.$type. '_' . $id;
        $prefix = $this->prefix . '_' . $app .'_'.$type. '_' . $id;
        Helper::delRedis($prefix);
        Helper::delRedis($listPrefix);
        $rating = new Voucher();
        if($type==='online-course'){
            $rating->online_course_id = $id;
        }elseif($type==='article'){
            $rating->article_id = $id;
        }else{
            $rating->detail_event_id = $id;
        }
        $rating->rate = (int) $request->input('rate') > 5 ? 5 : $request->input('rate');
        $rating->notes = strip_tags($request->input('notes'));
        $rating->title = strip_tags($request->input('title'));
        $rating->user_id = (int) $request->input('user_id');
        $rating->save();
        return $this->sendResponse(result: [], message: $message);
    }

}
