<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Library\Helper;
use App\Models\MembershipDuration;
use App\Models\MasterTransactionFee;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use App\Models\MasterBenefit;

class MembershipController extends BaseController
{
    protected $prefix;
    public function __construct()
    {
        $this->prefix = 'membership';
    }

    public function listPlan()
    {
        $prefix = $this->prefix . '_list-plan';
        $message = 'Daftar Paket Keanggotaan';

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $allMembershipPlan = MembershipPlan::select(['id', 'plan_name', 'limit_inquiry', 'description', 'is_default', 'allow_all_apps'])
            ->orderBy('order_number', 'ASC')->get();
        $allMembershipPlan = $allMembershipPlan->map( function($membershipPlan) {
            if(!$membershipPlan->allow_all_apps) {
                $membershipPlan->apps = $membershipPlan->apps;
            }

            return $membershipPlan;
        });
        
        // Helper::setRedis($prefix, json_encode($allMembershipPlan), 500);
        return $this->sendResponse(result: $allMembershipPlan, message: $message);
    }

    public function detailPlan($id)
    {
        $prefix = $this->prefix . '_plan-' . $id;
        $message = 'Detail Paket Keanggotaan';

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }
        $benefitMembership = MasterBenefit::findOrFail($id);
        $explode = explode(" ", $benefitMembership->title);
        $string = $explode[0];
        if (count($explode) > 1) {
	    if( $explode[1] == '(Default)' ){
		$string = $explode[0];
	    }else{
		$string = $explode[1];
	    }
        }
        
        $membershipPlan = MembershipPlan::select()
        ->where('plan_code', 'LIKE', '%'.$string.'%')->first();
        // dd($membershipPlan);
        if(!$membershipPlan->allow_all_apps) {
            $membershipPlan->apps = $membershipPlan->apps;
        }

        $membershipPlan->durations = $membershipPlan->durations;
        $transactionFee = MasterTransactionFee::select(['id', 'title', 'fee', 'fee_type'])->where('status', 'publish')->orderBy('id', 'ASC')->get()->toArray();
        $totalFee = 0;
        foreach($transactionFee as $key => $value) {
            $transactionFee[$key]['fee'] = $value['fee'];
            $totalFee += $value['fee'];
        }
        $membershipPlan->admin_fee = $totalFee;
        // dd($membershipPlan);
        // 
        // Helper::setRedis($prefix, json_encode($membershipPlan), 500);
        return $this->sendResponse(result: $membershipPlan, message: $message);
    }

    public function me(Request $request)
    {
        $prefix = $this->prefix . '_me';
        $message = 'Paket Keanggotaan yang Digunakan Saat Ini';
        $user = $request->get('session_user');

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $selectPlan = [
            'id',
            'plan_name',
            'limit_inquiry',
            'allow_all_apps',
            'description',
            'is_default',
        ];
        $currentMembershipPlan = MembershipPlan::find($user['membership_plan_id'], $selectPlan);
        if(!$currentMembershipPlan) {
            return $this->sendError('Paket keanggotaan saat ini tidak berlaku.');
        }

        $currentMembershipDuration = MembershipDuration::where('membership_plan_id', $currentMembershipPlan->id)->first();
        $currentMembershipPlan->duration = $currentMembershipDuration;

        if(!$currentMembershipPlan->allow_all_apps) {
            $currentMembershipPlan->apps = $currentMembershipPlan->apps;
        }
        
        // Helper::setRedis($prefix, json_encode($currentMembershipPlan), 500);
        return $this->sendResponse($currentMembershipPlan, $message);
    }
}
