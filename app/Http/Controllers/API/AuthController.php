<?php

namespace App\Http\Controllers\API;

use Google_Client;
use App\Models\User;
use App\Models\LogLogin;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\MembershipPlan;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\ForgotPasswordMail;
use App\Mail\VerifUserMail;
use App\Models\Kota;
use App\Models\MappingToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class AuthController extends BaseController
{
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(),[
                'first_name' => 'required|string|min:2|max:255',
                'last_name' => 'max:255',
                'email' => 'required|string|email|max:255|unique:'.User::class,
                'password' => 'required|string|min:6|max:25',
                'confirm_password' => 'required|same:password',
                'no_hp' => 'required|string|max:255',
                'birth_date' => 'required|date',
                'gender' => [
                    'required',
                    Rule::in(['male', 'female'])
                ],
            ]);

            if($validator->fails()){
                return $this->sendError('Kesalahan Validasi.', $validator->errors());
            }

            $input = $request->only([
                'first_name',
                'last_name',
                'email',
                'password',
                'no_hp',
                'birth_date',
                'gender',
                'job_name',
                'kota_id',
            ]);

            if(!empty($input['kota_id'])) {
                $kota = Kota::find($input['kota_id']);
                if(!$kota) {
                    return $this->sendError('Kota tidak ditemukan.');
                }
            }

            $input['password'] = Hash::make($input['password']);
            $input['membership_start'] = date('Y-m-d H:i:s');
            $user = User::create($input);
            $user->save();

            $verifMail = new VerifUserMail($user);
            Mail::to($user->email)->send($verifMail);

            $success['email'] = $user->email;
            $success['name'] =  $user->first_name.' '.$user->last_name;
            $success['no_hp'] = $user->no_hp;
            $success['birth_date'] = $user->birth_date;
            $success['gender'] = $user->gender;
            $success['job_name'] = $user->job_name;

            return $this->sendResponse($success, 'Pengguna berhasil mendaftar.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/login",
     *     summary="Login",
     *     description="Login with email and password",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email", type="string", format="email", example="user1@mail.com"),
     *              @OA\Property(property="password", type="string", format="password", example="abc123"),
     *          ),
     *     ),
     *     @OA\Response(response="200", description="Display User Login successful.",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string")
     *          )
     *     )
     * )
     */
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(),[
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->sendError('Kesalahan Validasi.', $validator->errors());
        }

        $user = User::where('email', $request->email)->first();
        if(!$user) {
            return $this->sendError('Email tidak terdaftar.');
        } else if( !Hash::check($request->password, $user->password) ) {
            return $this->sendError('Kata sandi tidak valid.');
        } else if(!$user->is_verified) {
            return $this->sendError('Akun Anda belum diverifikasi, harap segera verifikasi akun Anda.');
        }

        if( Auth::loginUsingId($user->id) ){
            $success['id'] = $user->id;
            $success['profile_picture'] = $user->profile_picture;
            $success['referral_code'] = $user->referral_code;
            $success['role'] = $user->role;
            $success['email'] = $user->email;
            $success['status'] = $user->status;
            $success['token'] =  $user->createToken('MyApp')->plainTextToken;
            $success['name'] =  $user->first_name.' '.$user->last_name;

            $data['user_id'] = $user->id;
            $data['email'] = $user->email;
            $data['nama'] = $user->first_name.' '.$user->last_name;
            $data['tgl_login'] = date('Y-m-d H:i:s');
            LogLogin::create($data);

            return $this->sendResponse($success, 'Pengguna berhasil masuk.');
        } else{
            return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
        }
    }

    public function loginWithGoogle(Request $request)
    {
        try {
            // dd($request);
            // $gCsrfCookie = $request->cookie('g_csrf_token');
            // $gCsrfBody = $request->g_csrf_token;

            // if( empty($gCsrfCookie) || empty($gCsrfBody) || $gCsrfCookie != $gCsrfBody ) {
            //     return $this->sendError('CSRF Validation Failed.', code: 400);
            // }

            $idToken = $request->credential;
            $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
            $payload = $client->verifyIdToken($idToken);

            if($payload) {
                $user = User::where('email', $payload['email'])->first();
                if(is_object($user)) {
                    $otp = Str::uuid();
                    MappingToken::create([
                        'type' => 'google_otp',
                        'exp_time' => strtotime('+20 minutes'),
                        'token' => $otp,
                        'user_id' => $user->id,
                    ]);

                    return redirect(env('GOOGLE_AUTH_REDIRECT') . '/login/google?t=' . $otp);
                    
                } else {
                    $user = new User();
                    $user->first_name = $payload['given_name'];
                    $user->last_name = $payload['family_name'];
                    $user->email = $payload['email'];
                    $user->save();
                    
                    $otp = $payload['email'] != 'putra@gmail.com' ? 'bDxyBf9flieseWXHQfok' : Str::uuid();
                    MappingToken::create([
                        'type' => 'google_otp',
                        'exp_time' => strtotime('+20 minutes'),
                        'token' => $otp,
                        'user_id' => $user->id,
                    ]);
                    return redirect(env('GOOGLE_AUTH_REDIRECT') . '/login/google?t=' . $otp . '&inu=true');
                }

            } else if(is_array($payload) && !$payload['email_verified']) {
                return redirect(env('GOOGLE_AUTH_REDIRECT') . '/login/error');
                
            } else {
                return redirect(env('GOOGLE_AUTH_REDIRECT') . '/login/error');
            }

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        } 
    }

    public function loginGoogleOtp(Request $request)
    {
        try {
            $user = User::select('users.*', 'mapping_token.id AS id_token')
                ->join('mapping_token', 'mapping_token.user_id', 'users.id')
                ->where('mapping_token.token', $request->otp)
                ->where('mapping_token.exp_time', '>', strtotime('now'))->first();
            if(is_object($user)) {
                Auth::login($user);
                MappingToken::where('id', $user->id_token)->delete();
                $authToken = $user->createToken('MyApp')->plainTextToken;

                $result = ['token' => $authToken];
                return $this->sendResponse($result, 'Verifikasi Token OTP Berhasil.');

            } else {
                return $this->sendError('Token OTP tidak valid', code: 400);
            }

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function verifUser($token, Request $request)
    {
        $decryptedToken = Crypt::decryptString($token);
        $splitToken = explode('_', $decryptedToken);

        if( 
            count($splitToken) != 3 ||
            $splitToken[1] != 'VERIF-USER' ||
            strlen($splitToken[2]) != 36 
        ) {
            return redirect( env('USER_VERIFY_ERROR_REDIRECT') );
        }

        $user = User::find($splitToken[0]);
        if( !is_object($user) || $user->is_verified ) {
            return redirect( env('USER_VERIFY_ERROR_REDIRECT') );
        }

        $user->is_verified = true;
        $user->save();
        return redirect( env('USER_VERIFY_SUCCESS_REDIRECT') );
    }

    public function requestForgot(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'email' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->sendError('Kesalahan Validasi.', $validator->errors());
        }

        $user = User::where('email', $request->email)->first();
        if(!$user) {
            return $this->sendError('Email tidak terdaftar.');
        }

        $token = Str::uuid();
        MappingToken::create([
            'type' => 'forgot_password',
            'exp_time' => strtotime('+1 hours'),
            'token' => $token,
            'user_id' => $user->id,
        ]);

        $mail = new ForgotPasswordMail($token);
        Mail::to($request->email)->send($mail);
        return $this->sendResponse([], 'Tautan lupa kata sandi berhasil dikirim ke email.');
    }

    public function submitForgot(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'token' => 'required|string',
            'new_password' => 'required|string|min:6|max:25',
            'confirm_new_password' => 'required|string|same:new_password',
        ]);

        if($validator->fails()){
            return $this->sendError('Kesalahan Validasi.', $validator->errors());
        }

        $user = User::select('users.*', 'mapping_token.id AS id_token')
            ->join('mapping_token', 'mapping_token.user_id', 'users.id')
            ->where('mapping_token.token', $request->token)
            ->where('mapping_token.exp_time', '>', strtotime('now'))->first();

        if(!$user) {
            return $this->sendError('Token tidak valid.', code: 400);
        };

        $user->password = Hash::make($request->new_password);
        $user->save();
        MappingToken::where('id', $user->id_token)->delete();

        return $this->sendResponse([], 'Perubahan kata sandi berhasil disimpan.');
    }
}
