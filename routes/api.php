<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\TagController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\SubCategoryController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\BannerController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OnlineCourseController;
use App\Http\Controllers\API\WishlistController;
use App\Http\Controllers\API\TransactionXenditController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });



Route::middleware('auth:sanctum')->group( function () {
    Route::resource('tag',TagController::class);
});

Route::controller(AuthController::class)->group(function(){
    Route::post('/auth/register', 'register')->middleware(['valid_csrf']);
    Route::post('/auth/login', 'login')->middleware(['valid_csrf']);
});

Route::get('/banner', [BannerController::class, 'index']);

Route::group([], __DIR__.'/routes_putra.php');
Route::group([], __DIR__.'/routes_randika.php');
Route::group([], __DIR__.'/routes_triaji.php');
