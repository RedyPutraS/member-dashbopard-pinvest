<?php

use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\SubCategoryController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\SpotifyController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\SubCommentController;
use App\Http\Controllers\API\LikeController;
use App\Http\Controllers\API\LikeShareController;
use App\Http\Controllers\API\VoucherController;
use App\Http\Controllers\API\InstructorController;
use App\Http\Controllers\API\YoutubeController;
use App\Http\Controllers\API\InquiryController;
use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\CommentLikeController;
use App\Http\Controllers\API\CsrfController;
use App\Http\Controllers\API\SubCommentLikeController;
use App\Http\Controllers\API\TransactionXenditController;
use App\Http\Controllers\API\MembershipGalleryController;
use App\Mail\NotifMembershipMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'transaction'], function() {
    Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
        Route::post('/createV3', [TransactionXenditController::class, 'createV3'])->middleware(['valid_csrf']);
        Route::get('/detailV3', [TransactionXenditController::class,'detailTransactionV3']);
        Route::get('/listAvailableBank', [TransactionXenditController::class,'listBankXendit'])->middleware(['token_without_login']);
        Route::post('/simulateVA', [TransactionXenditController::class,'simulateVA']);
        Route::post('/updatePaymentExpire/{id}', [TransactionXenditController::class,'updatePaymentExpire']);
        Route::get('/statusPaymentOrder', [TransactionXenditController::class,'statusPaymentOrder']);
    });
    Route::post('/webhook/success', [TransactionXenditController::class,'webhookSuccess']);
    Route::post('/webhook/payment_method', [TransactionXenditController::class,'webhookPaymentMethod']);


    Route::get('/statusXendit', [TransactionXenditController::class,'getStatusPaymentXendit']);
    Route::post('/statusXendit', [TransactionXenditController::class,'getStatusPaymentXendit']);
});

Route::get('/membership-gallery/{id}', [MembershipGalleryController::class, 'Gallery']);
Route::get('/testing-heula', function () {
    return 'Ini adalah teks yang ditampilkan sebagai respons dari route /testing-heula-nya';
});
