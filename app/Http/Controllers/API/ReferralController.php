<?php

namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Affiliate;
use App\Models\User;


class ReferralController extends Controller
{
    public function __construct()
    {


    }
    public static function store(Request $request,$type,$id)
    {
        if(!in_array($type ,APP_TYPE ))
        {
            return false;
        }
        $referral = $request->get('referral');
        if(!$referral){
            return false;
        }
        $user = $request->get('session_user') ?? null;
        $userReferral = User::where('referral_code', '=', $referral)->firstOrFail();
        $user_id = null;
        $ip = $request->ip();
        if($user){
            $user_id = $user['id'];
            if($user_id == $userReferral->id){

                return false;
            }
            if($referral==$user['referral_code']){
                return false;
            }
        }

        $affilate = new Affiliate();
        $findAffilate = Affiliate::select('id');
        if($type=='event'){
            $affilate->event_id = $id;
            $findAffilate = $findAffilate->where('affiliator',$userReferral->id)
                ->where('event_id',$id);

        }elseif($type=='online-course'){
            $affilate->event_id = $id;
            $findAffilate = $findAffilate->where('affiliator',$userReferral->id)
                ->where('online_course_id',$id);

        }elseif($type=='article'){
            $affilate->article_id = $id;
            $findAffilate = $findAffilate->where('affiliator',$userReferral->id)
                ->where('article_id',$id);
        }elseif($type=='youtube'){
            $affilate->youtube_id = $id;
            $findAffilate = $findAffilate->where('affiliator',$userReferral->id)
                ->where('youtube_id',$id);
        }else {
            $affilate->spotify_id = $id;
            $findAffilate = $findAffilate->where('affiliator',$userReferral->id)
                ->where('spotify_id',$id);
        }
        if($user){
            $findAffilate =  $findAffilate->where('target_affiliate',$user_id);
        }else{
            $findAffilate =  $findAffilate->where('ip',$ip);
        }
        $findAffilate= $findAffilate->first();
        if($findAffilate){
            return false;
        }
        $affilate->affiliator = $userReferral->id;
        $affilate->ip = $ip;
        if($user){
            $affilate->target_affiliate = $user_id;
        }
        if($affilate->save()){
            return true;
        }

        return false;
    }
}
