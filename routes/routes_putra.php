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
use Illuminate\Support\Facades\Route;



Route::get('/{name}/article', [ArticleController::class,'index']);
Route::get('/{name}/article/{id}', [ArticleController::class,'detail'])->middleware(['token_without_login']);

Route::get('/app', [ApplicationController::class,'index']);


//Route::get('/app', [ApplicationController::class,'index']);
Route::get('/category/by-app/{id}', [CategoryController::class,'byAppId']);
Route::get('/category/by-name/{name}', [CategoryController::class,'queryByCategoryByApp']);
Route::get('/subcategory/by-category/{id}', [SubCategoryController::class,'byCategoryId']);
Route::get('/sanctum/csrf-cookie', [CsrfController::class,'generate']);

Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
    Route::post('/inquiry', [InquiryController::class,'create'])->middleware(['valid_csrf']);
    Route::post('/{app}/rating/{id}', [RatingController::class,'store'])->middleware(['valid_csrf']);
    Route::get('/{app}/rating/me/{id}', [RatingController::class,'myRating']);
    Route::post('/{app}/comment/{id}', [CommentController::class,'store'])->middleware(['valid_csrf']);
    Route::post('/{app}/comment/update/{id}', [CommentController::class,'update'])->middleware(['valid_csrf']);
    Route::delete('/{app}/comment/delete/{id}', [CommentController::class,'destroy']);
    
    Route::post('/{app}/subcomment/{id}', [SubCommentController::class,'store']);
    Route::post('/{app}/subcomment/update/{id}', [SubCommentController::class,'update'])->middleware(['valid_csrf']);
    Route::delete('/{app}/subcomment/delete/{id}', [SubCommentController::class,'destroy']);
    Route::post('/{app}/comment-like/{id}', [CommentLikeController::class,'store'])->middleware(['valid_csrf']);
    Route::delete('/{app}/comment-dislike/{id}', [CommentLikeController::class,'destroy']);

    Route::post('/subcomment-like/{id}', [SubCommentLikeController::class,'store'])->middleware(['valid_csrf']);
    Route::delete('/subcomment-dislike/{id}', [SubCommentLikeController::class,'destroy']);

    Route::post('/{app}/comment/{id}', [CommentController::class,'store']);
    Route::delete('/{app}/comment-delete/{id}', [CommentController::class,'destroy']);

    Route::post('/{app}/likeshare/{id}', [LikeShareController::class,'store']);
    Route::get('/{app}/detail-likeshare/{id}', [LikeShareController::class,'detail']);



  


});


Route::group(['prefix' => 'transaction'], function() {
    Route::group(['middleware' => ['auth:sanctum','valid_token']], function () {
        Route::post('/create', [TransactionController::class, 'create'])->middleware(['valid_csrf']);
        Route::post('/create-v2', [TransactionController::class, 'createV2'])->middleware(['valid_csrf']);
        Route::get('/check', [TransactionController::class,'statusPayment']);
        Route::post('/checkout', [TransactionController::class,'checkout'])->middleware(['valid_csrf']);
        Route::get('/detail', [TransactionController::class,'detailTransactionV2']);
        Route::get('/download-ticket/{id}', [TransactionController::class,'downloadTicket']);

        Route::get('/list-bank', [TransactionController::class, 'listBank']);
        Route::get('/list-transaction', [TransactionController::class,'listTransaction']);
    });

    Route::get('/list-tab', [TransactionController::class, 'listTab']);

    Route::post('/inquiry', [TransactionController::class,'inquiry']);
    Route::get('/inquiry', [TransactionController::class,'inquiry']);

    Route::get('/status', [TransactionController::class,'getStatusPayment']);
    Route::post('/status', [TransactionController::class,'getStatusPayment']);
});


Route::get('/{app}/subcomment/{id}', [SubCommentController::class,'index']);
Route::get('/comment/{id}', [CommentController::class,'index']);

Route::get('/{app}/rating/{id}', [RatingController::class,'list']);
Route::get('/{app}/rating/count/{id}', [RatingController::class,'index']);


Route::get('/{app}/likeshare/{id}', [LikeShareController::class,'index']);


// notes

Route::get('/{app}/comment/{id}', [CommentController::class,'index']);


Route::get('/{name}/event', [EventController::class,'index'])->middleware(['token_without_login']);
Route::get('/{name}/event/{id}', [EventController::class,'detail'])->middleware(['token_without_login']);

//Route::get('/{name}/event', [EventController::class,'index']);
//Route::get('/{name}/event/{id}', [EventController::class,'detail']);

Route::get('/instructor/{id}', [InstructorController::class,'byDetailEventId']);


Route::get('/picast/spotify/token', [SpotifyController::class,'token']);
Route::get('/picast/spotify/episodes', [SpotifyController::class,'episodes']);
Route::get('/picast/youtube', [YoutubeController::class,'listVideoByChannelId']);
Route::get('/picast/youtube-playlist', [YoutubeController::class,'playlist']);
Route::get('/picast/youtube-playlist/{id}', [YoutubeController::class,'playlistItem']);






//notes
Route::get('/{name}/voucher/list', [VoucherController::class,'list']);
Route::get('/{name}/voucher/{id}', [VoucherController::class,'detail']);
Route::get('/voucher', [VoucherController::class,'all']);

Route::get('/faq/general', [FaqController::class,'general']);
Route::get('/faq/{name}', [FaqController::class,'byApp']);






