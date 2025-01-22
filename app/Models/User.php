<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'no_hp',
        'birth_date',
        'gender',
        'status',
        'job_name',
        'kota_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static function isMembership($planId) {
        if(empty($planId)) return false;

        $membershipPlanUsed = MembershipPlan::find($planId);
        if(
            is_object($membershipPlanUsed) &&
            (!$membershipPlanUsed->is_default || strtotime($membershipPlanUsed->membership_exp) > strtotime('now'))
        ) {
            return true;
        }

        return false;
    }

    public static function getProfilePict($profilePict, $gender) {
        if(!empty($profilePict)) return $profilePict;
        return $gender == 'male' ? env('USER_DEFAULT_PROFILE_PICT_MALE') : env('USER_DEFAULT_PROFILE_PICT_FEMALE');
    }

    public static function validateLimitInquiry($user, $masterAppId) {
        $membershipPlan = !is_null($user['membership_plan_id']) 
            ?  MembershipPlan::find($user['membership_plan_id'])
            : MembershipPlan::where('is_default', true)->first();

        if(!$membershipPlan->allow_all_apps) {
            $isAllowApps = MappingApp::where('membership_plan_id', $membershipPlan->id)
                ->where('master_app_id', $masterAppId)->count() > 0;
            if(!$isAllowApps) {
                return ['valid' => false, 'message' => 'Aplikasi yang ingin Anda tanyakan tidak diizinkan.'];
            }
        }

        $membershipStart = !is_null($user['membership_exp']) ? $user['membership_exp'] : date('Y-m-d', strtotime($user['created_at']));
        $inquirySubmittedCount = Inquiry::where('created_by', $user['id']) 
            ->where(DB::raw('DATE(created_at)'), '>=', $membershipStart)->count();
        if($inquirySubmittedCount >= $membershipPlan->limit_inquiry) {
            return ['valid' => false, 'message' => 'Batasan pertanyaan telah terlampaui.'];
        }

        return ['valid' => true];
    }
}
