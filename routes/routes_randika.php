<?php

use App\Http\Controllers\API\AccountController;
use App\Http\Controllers\API\ActivityController;
use App\Http\Controllers\API\AdsController;
use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BannerPopupController;
use App\Http\Controllers\API\CartV2Controller;
use App\Http\Controllers\API\CollabsController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\InquiryController;
use App\Http\Controllers\API\PartnerController;
use App\Http\Controllers\API\SpotifyController;
use App\Http\Controllers\API\YoutubeController;
use App\Http\Controllers\API\MasterBenefitController;
use App\Http\Controllers\API\MasterKotaController;
use App\Http\Controllers\API\MembershipController;
use App\Http\Controllers\API\NewsletterController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\WishlistV2Controller;
use App\Http\Controllers\API\OnlineCourseController;
use App\Http\Controllers\API\SearchController;
use App\Mail\NotifMembershipMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/verif-user/{token}', [AuthController::class, 'verifUser']);

Route::get('/gallery', [FaqController::class, 'gallery']);

Route::get('/partner', [PartnerController::class,'index']);

Route::group(['prefix' => 'picast'], function() { 
    Route::get('/youtube/categories', [YoutubeController::class,'listCategory']);
    Route::get('/youtube/video-detail/{id}', [YoutubeController::class,'detail']);
    
    Route::get('/spotify/detail-playlist', [SpotifyController::class,'detailPlaylist']);
    Route::get('/spotify/categories', [SpotifyController::class,'listCategory']);
    Route::get('/spotify/episodes/{id}', [SpotifyController::class,'detail']);
});

Route::get('/inquiry/questions', [InquiryController::class,'listQuestions']);

Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
    Route::group(['prefix' => 'account'], function() {
        Route::get('/', [AccountController::class, 'index']);
        Route::post('/change-profile', [AccountController::class, 'changeProfile'])->middleware(['valid_csrf']);
        Route::post('/change-profile-picture', [AccountController::class, 'changeProfilePicture'])->middleware(['valid_csrf']);
        Route::post('/change-password', [AccountController::class, 'changePassword'])->middleware(['valid_csrf']);
    });
});

// Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
//     Route::get('/cart', [CartController::class, 'index']);
//     Route::post('/cart/add', [CartController::class, 'addCart'])->middleware(['valid_csrf']);
//     Route::delete('/cart/remove/{id}', [CartController::class, 'removeCart']);

//     Route::get('/wishlist', [WishlistController::class, 'index']);
//     Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->middleware(['valid_csrf']);
// });

Route::group(['prefix' => 'auth/login-google'], function() {
    Route::post('/', [AuthController::class, 'loginWithGoogle']);
    Route::post('/verify-otp', [AuthController::class, 'loginGoogleOtp']);
});

Route::group(['prefix' => 'master'], function() { 
    Route::get('/benefit', [MasterBenefitController::class, 'index']);
    Route::get('/kota', [MasterKotaController::class, 'index']);
});

Route::group(['prefix' => 'membership'], function() { 
    Route::get('/list-plan', [MembershipController::class, 'listPlan']);
    Route::get('/detail-plan/{id}', [MembershipController::class, 'detailPlan']);
    Route::get('/me', [MembershipController::class, 'me'])->middleware(['auth:sanctum','valid_token']);
});

Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
    Route::group(['prefix' => 'notification'], function() { 
        Route::get('/all', [NotificationController::class, 'index']);
        Route::post('/read', [NotificationController::class, 'read'])->middleware(['auth:sanctum','valid_token']);
    });
});

Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
    Route::group(['prefix' => 'cart-v2'], function() {
        Route::get('/', [CartV2Controller::class, 'index']); 
        Route::post('/add', [CartV2Controller::class, 'store'])->middleware(['valid_csrf']);
        Route::post('/update/{id}', [CartV2Controller::class, 'update'])->middleware(['valid_csrf']);
        Route::post('/move-wishlist/{id}/{slug}/{event}', [CartV2Controller::class, 'moveWishlist'])->middleware(['valid_csrf']);
        Route::post('/check-voucher', [CartV2Controller::class, 'checkVoucher'])->middleware(['valid_csrf']);
        Route::delete('/remove/{id}', [CartV2Controller::class, 'destroy']);
    });

    Route::group(['prefix' => 'wishlist-v2'], function() {
        Route::get('/', [WishlistV2Controller::class, 'index']);
        Route::post('/add', [WishlistV2Controller::class, 'store'])->middleware(['valid_csrf']);
        Route::delete('/remove/{id}', [WishlistV2Controller::class, 'destroy']);
    });
});

Route::group(['prefix' => 'ads'], function() {
    Route::get('/', [AdsController::class, 'index']);
    Route::get('/by-app/{app}', [AdsController::class, 'byApp']);
});

Route::prefix('pilearning/online-course')->group( function() {
    Route::get('/', [OnlineCourseController::class,'index'])->middleware(['token_without_login']); 
    Route::get('/detail/{id}', [OnlineCourseController::class,'detail'])->middleware(['token_without_login']);
    Route::get('/play/{id}', [OnlineCourseController::class,'play'])->middleware(['auth:sanctum','valid_token']);
});

Route::group(['prefix' => 'banner-popup'], function() {
    Route::get('/', [BannerPopupController::class, 'index']);
    Route::get('/by-app/{app}', [BannerPopupController::class, 'byApp']);
});

Route::group(['prefix' => 'auth/forgot-password'], function() {
    Route::post('/request', [AuthController::class, 'requestForgot'])->middleware(['valid_csrf']);
    Route::post('/submit', [AuthController::class, 'submitForgot'])->middleware(['valid_csrf']);
});

Route::get('/search', [SearchController::class, 'index']);

Route::group(['prefix' => 'collabs'], function() {
    Route::get('/', [CollabsController::class, 'index']);
    Route::get('/detail/{app}', [CollabsController::class, 'detail']);
    Route::get('/inquiry-questions', [CollabsController::class, 'inquiryQuestions']);
    Route::post('/create-inquiry', [CollabsController::class, 'createInquiry'])->middleware(['auth:sanctum','valid_token', 'valid_csrf']);  
});

Route::group(['prefix' => 'syarat-ketentuan'], function() {
    Route::get('/', [CollabsController::class, 'syarat']);
    Route::get('/detail/{app}', [CollabsController::class, 'detail']);
    Route::get('/inquiry-questions', [CollabsController::class, 'inquiryQuestions']);
    Route::post('/create-inquiry', [CollabsController::class, 'createInquiry'])->middleware(['auth:sanctum','valid_token', 'valid_csrf']);  
});


// My Acticity
Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
    Route::get('/my-activity', [ActivityController::class, 'index']);
});

Route::post('/subscribe-newsletter', [NewsletterController::class, 'subscribe'])->middleware(['valid_csrf']);

Route::group(['middleware' => ['auth:sanctum','valid_token','valid_csrf']], function () {
    Route::post('/picircle/create-article', [ArticleController::class, 'create']);
});

Route::get('/test-ran', function() {
    $notifMail = new NotifMembershipMail();
    Mail::to('randika.rosyid2@gmail.com')->send($notifMail);
    // return view('mail.notif-membership');
});