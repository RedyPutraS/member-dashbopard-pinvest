<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\API\UploadController;
use App\Http\Controllers\Controller;
use App\Models\Kota;
use App\Models\MasterBenefit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class AccountController extends BaseController
{
    public function changeProfile(Request $request)
    {
        try {
            $sessionUser = $user = $request->get('session_user');
            $user = User::find($sessionUser['id']);

            $validator = Validator::make($request->all(),[
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|string|email|max:255' . ( $user->email != $request->email ? '|unique:'.User::class : '' ),
                'no_hp' => 'required|string|max:255',
                'birth_date' => 'required|date',
                'gender' => [
                    'required',
                    Rule::in(['male', 'female'])
                ],
            ]);

            if($validator->fails()){
                return $this->sendError('Validation Error.', $validator->errors());
            }

            $input = $request->only([
                'first_name',
                'last_name',
                'email',
                'no_hp',
                'birth_date',
                'gender',
                'job_name',
            ]);

            $user->update($input);
            return $this->sendResponse($input, 'Profil Akun berhasil disimpan.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function index(Request $request)
    {
        $sessionUser = $request->get('session_user');
        if($sessionUser['status'] == 'free'){
            $img_status = MasterBenefit::find(1)->image;
        }else{
            $img_status = MasterBenefit::where('title', $sessionUser['status'])->first()->image;
        }
        $sessionUser = [
            'id' => $sessionUser['id'],
            'first_name' => $sessionUser['first_name'],
            'last_name' => $sessionUser['last_name'],
            'email' => $sessionUser['email'],
            'no_hp' => $sessionUser['no_hp'],
            'birth_date' => $sessionUser['birth_date'],
            'gender' => $sessionUser['gender'],
            'status' => $sessionUser['status'],
            'img_status' => $img_status,
            'is_blocked' => $sessionUser['is_blocked'],
            'profile_picture' => User::getProfilePict($sessionUser['profile_picture'], $sessionUser['gender']),
            'referral_code' => $sessionUser['referral_code'],
            'job_name' => $sessionUser['job_name'],
            'kota' => Kota::find($sessionUser['kota_id'], ['kota']),
        ];

        return $this->sendResponse($sessionUser, 'Daftar Data Profil Akun.');
    }

    public function changePassword(Request $request)
    {
        try {
            $user = $request->get('session_user');
            $user = User::find($user['id']);

            $validator = Validator::make($request->all(),[
                'password' => 'current_password',
                'new_password' => 'required|string|min:6|max:25',
                'confirm_new_password' => 'required|same:new_password',
            ], [
                'password.current_password' => 'Kata sandi lama saat ini salah.',
                'new_password.required' => 'Kata sandi baru wajib diisi.',
                'new_password.min' => 'Kata sandi baru harus minimal 6 karakter.',
                'new_password.max' => 'Kata sandi baru tidak boleh lebih dari 25 karakter.',
                'confirm_new_password.required' => 'Konfirmasi kata sandi baru wajib diisi.',
                'confirm_new_password.same' => 'Konfirmasi kata sandi baru harus sama dengan kata sandi baru.',
            ]);
            
            if($validator->fails()){
                return $this->sendError('Kesalahan Validasi.', $validator->errors());
            }
            
            $input = $request->only(['new_password']);
            $input['password'] = Hash::make($input['new_password']);
            $user->update($input);
            Auth::guard('web')->logout();

            return $this->sendResponse([], 'Kata sandi akun berhasil disimpan, Silahkan login ulang');
            // return redirect('/');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function changeProfilePicture(Request $request)
    {
        try {
            $user = $request->get('session_user');
            $user = User::find($user['id']);

            $validator = Validator::make($request->all(), ['profile_picture' => 'required|image']);
            if($validator->fails()){
                return $this->sendError('Validation Error.', $validator->errors());
            }
            
            $uploadOss = new UploadController();
            $uploadOssProfilePict = $uploadOss->ossUpload(file: $request->profile_picture, prefix: 'user_profile_picture_');
            $profilePictFileName = $uploadOssProfilePict['url'];

            $user->profile_picture = $profilePictFileName;
            $user->save();
            return $this->sendResponse([], 'Gambar Profil Akun berhasil disimpan.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }
}
