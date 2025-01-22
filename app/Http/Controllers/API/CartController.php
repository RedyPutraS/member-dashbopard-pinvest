<?php

namespace App\Http\Controllers\API;

//use Illuminate\Http\Request;

use App\Library\Helper;
use App\Models\Article;
use App\Models\Cart;
use App\Models\DetailEvent;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends BaseController
{
    // public function byCategoryId(int $id) : object
    // {
    //     return $this->sendResponse(result: self::queryByCategoryId($id),message: 'List SubCategory');
    // }
    // public function queryByCategoryId (int  $id): array{
    //     $detail = SubCategory::select('id','subcategory_name','alias')
    //     ->where('status','publish')
    //     ->where('master_category_id',$id)->get()->toArray();

    //     return $detail;
    // }

    public function __construct()
    {
        $this->prefix = 'carts';
        $this->tabs = [
          'online_course', 
          'webinar', 
          'event', 
          'seminar', 
          'workshop'
        ];
    }

    public function index(Request $request)
    {
        $tab = strtolower($request->input('tab'));
        if(empty($tab)) {
            return $this->sendError('Bidang tab wajib diisi', code: 400);

        } else if( !in_array($tab, $this->tabs) ) {
            return $this->sendError('Bidang tab tidak valid', code: 400);
        }

        $message = 'Daftar Keranjang '.$tab;
        if($tab == 'online_course'){
            $select = [
                'carts.id',
                'carts.qty',
                'online_course.id AS online_course_id',
                'online_course.image',
                'online_course.title',
                'online_course.meta_description as description',
                'users.username AS author',
                DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate'),
                'online_course.price'
            ];
          
            $wishlist = Cart::select($select)
              ->join('online_course', 'carts.content_id','=', 'online_course.id')
              ->join('master_category', 'carts.category_id','=', 'master_category.id')
              ->join('users', 'online_course.created_by','=', 'users.id')
              ->join('master_app', 'carts.app_id','=', 'master_app.id')
              ->leftjoin('rating', 'online_course.id','=', 'rating.online_course_id');
            
            $app_id = 10;
            $category_id = 11;
            $wishlist = $wishlist
              ->where('carts.app_id','=',$app_id)
              ->where('carts.category_id','=',$category_id)
              ->where('carts.user_id','=',Auth::id())
              ->where('online_course.status','=','publish');
            
            $groupBy = [
                'master_category.category_name',
                'online_course.title',
                'online_course.id',
                'online_course.image',
                'online_course.meta_description',
                'online_course.price',
                'users.username'
            ];

        } else {
            $select = [
                'carts.id',
                'carts.qty',
                'event.type',
                'detail_event.id AS detail_event_id',
                'detail_event.image AS thumbnail_image',
                'event.master_category_id',
                'event.master_subcategory_id',
                'master_category.category_name',
                'master_subcategory.subcategory_name',
                'master_category.alias AS category_name_alias',
                'master_subcategory.alias AS subcategory_name_alias',
                'detail_event.description',
                'detail_event.price',
                'event.id AS event_id',
                'detail_event.title',
                'event.province',
                'detail_event.date',
                'detail_event.end_date',
                'event.city',
                'event.google_location',
                'event.place',
                'event.address',
                'detail_event.price',
                'detail_event.promo_price',
                'master_app.app_name',
                DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate'),
            ];
            
            $wishlist = Cart::select($select)
                ->join('detail_event', 'carts.content_id','=', 'detail_event.id')
                ->join('event', 'detail_event.event_id','=', 'event.id')
                ->join('master_category', 'event.master_category_id','=', 'master_category.id')
                ->join('master_app', 'event.master_app_id','=', 'master_app.id')
                ->leftjoin('rating', 'detail_event.id','=', 'rating.event_id')
                ->leftjoin('master_subcategory', 'event.master_subcategory_id','=', 'master_subcategory.id');
            
            if($tab == 'webinar'){
                $app_id = 10;
                $category_id = 5;
                $wishlist = $wishlist
                  ->where('carts.app_id','=',$app_id)
                  ->where('carts.category_id','=',$category_id);

            }else if($tab == 'event'){
                $app_id = 15;
                $wishlist = $wishlist->where('carts.app_id','=',$app_id);

            }else if($tab == 'seminar'){
                $app_id = 10;
                $category_id = 9;
                $wishlist = $wishlist
                    ->where('carts.app_id','=',$app_id)
                    ->where('carts.category_id','=',$category_id);

            }else if($tab == 'workshop'){
                $app_id = 10;
                $category_id = 10;
                $wishlist = $wishlist
                    ->where('carts.app_id','=',$app_id)
                    ->where('carts.category_id','=',$category_id);
            }

            $wishlist = $wishlist
                ->where('carts.user_id','=',Auth::id())
                ->where('event.status','=','publish');
            
            $groupBy = [
                'carts.id',
                'event.type',
                'detail_event.id',
                'event.master_category_id',
                'event.master_subcategory_id',
                'master_category.category_name',
                'master_subcategory.subcategory_name',
                'master_category.alias',
                'master_subcategory.alias',
                'event.id',
                'master_app.app_name'
            ];
        }

        $wishlist = $wishlist->groupBy($groupBy);
        $wishlist = $wishlist->get()->toArray();
        
        return $this->sendResponse(result: $wishlist,message: $message);
    }

    public function addCart(Request $request)
    {
        try {
            $tab = strtolower($request->input('tab'));
            $contentId = $request->input('content_id');
            $qty = $request->input('qty');
            
            if(empty($tab)) {
                return $this->sendError('Bidang tab wajib diisi', code: 400);
            
            } else if(empty($qty)) {
                return $this->sendError('Kolom Qty wajib diisi', code: 400);

            } else if( !in_array($tab, $this->tabs) ) {
                return $this->sendError('Bidang tab tidak valid', code: 400);
            }

            if($tab == 'online_course') {
                $article = Article::find($contentId);
                if(is_null($article)) {
                    return $this->sendError('Data konten tidak ditemukan');
                }
                
                $appId = $article->master_app_id;
                $categoryId = $article->master_category_id;

            } else {
                $detailEvent = DetailEvent::find($contentId);
                if(is_null($detailEvent)) {
                    return $this->sendError('Data konten tidak ditemukan');
                }

                $event = $detailEvent->event;
                $appId = $event->master_app_id;
                $categoryId = $event->master_category_id;
            }

            $user = $request->get('session_user');
            $wishlist = new Cart();

            $wishlist->category_id = $categoryId;
            $wishlist->user_id = $user['id'];
            $wishlist->content_id = $contentId;
            $wishlist->app_id = $appId;
            $wishlist->qty = $qty;
            
            $wishlist->save();
            return $this->sendResponse($wishlist, 'Keranjang berhasil ditambahkan.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function removeCart($id)
    {
        try {
            $cart = Cart::find($id);
            if(is_null($cart)) {
                return $this->sendError('Barang keranjang tidak ditemukan');
            }
            
            $cart->delete();
            return $this->sendResponse([], 'Hapus item keranjang dengan sukses.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }   
    }
}
