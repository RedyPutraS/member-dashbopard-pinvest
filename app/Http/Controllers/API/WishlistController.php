<?php

namespace App\Http\Controllers\API;

use App\Models\Article;
use App\Models\DetailEvent;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Models\SubCategory;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use App\Models\MappingInstructor;
use Illuminate\Support\Facades\DB;

class WishlistController extends BaseController
{
    protected $prefix;
    protected $tabs;

    public function __construct()
    {
        $this->prefix = 'wishlist';
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

        $message = 'Daftar Wishlist '.$tab;
        if($tab == 'online_course'){
            $select = [
                'online_course.id',
                'online_course.image',
                'online_course.title',
                'online_course.meta_description as description',
                'users.username AS author',
                DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate'),
                'online_course.price',
                'master_category.alias AS category_name_alias',
                'master_app.alias AS app_name_alias',
            ];
          
            $wishlist = Wishlist::select($select)
              ->join('online_course', 'wishlist.content_id','=', 'online_course.id')
              ->join('master_app', 'online_course.master_app_id','=', 'master_app.id')
              ->join('master_category', 'wishlist.category_id','=', 'master_category.id')
              ->join('users', 'online_course.created_by','=', 'users.id')
              ->join('master_app', 'wishlist.app_id','=', 'master_app.id')
              ->leftjoin('rating', 'online_course.id','=', 'rating.online_course_id');
            
            $app_id = 10;
            $category_id = 11;
            $wishlist = $wishlist
              ->where('wishlist.app_id','=',$app_id)
              ->where('wishlist.category_id','=',$category_id)
              ->where('wishlist.user_id','=',Auth::id())
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
                'master_app.app_name_alias',
                DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate'),
            ];
            
            $wishlist = Wishlist::select($select)
                ->join('detail_event', 'wishlist.content_id','=', 'detail_event.id')
                ->join('event', 'detail_event.event_id','=', 'event.id')
                ->join('master_category', 'event.master_category_id','=', 'master_category.id')
                ->join('master_app', 'event.master_app_id','=', 'master_app.id')
                ->leftjoin('rating', 'detail_event.id','=', 'rating.event_id')
                ->leftjoin('master_subcategory', 'event.master_subcategory_id','=', 'master_subcategory.id');
            
            if($tab == 'webinar'){
                $app_id = 10;
                $category_id = 5;
                $wishlist = $wishlist
                  ->where('wishlist.app_id','=',$app_id)
                  ->where('wishlist.category_id','=',$category_id);

            }else if($tab == 'event'){
                $app_id = 15;
                $wishlist = $wishlist->where('wishlist.app_id','=',$app_id);

            }else if($tab == 'seminar'){
                $app_id = 10;
                $category_id = 9;
                $wishlist = $wishlist
                    ->where('wishlist.app_id','=',$app_id)
                    ->where('wishlist.category_id','=',$category_id);

            }else if($tab == 'workshop'){
                $app_id = 10;
                $category_id = 10;
                $wishlist = $wishlist
                    ->where('wishlist.app_id','=',$app_id)
                    ->where('wishlist.category_id','=',$category_id);
            }

            $wishlist = $wishlist
                ->where('wishlist.user_id','=',Auth::id())
                ->where('event.status','=','publish');
            
            $groupBy = [
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

    public function addWishlist(Request $request)
    {
        try {
            $tab = strtolower($request->input('tab'));
            $contentId = $request->input('content_id');
            if(empty($tab)) {
                return $this->sendError('Bidang tab wajib diisi', code: 400);

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
            $wishlist = new Wishlist();

            $wishlist->category_id = $categoryId;
            $wishlist->user_id = $user['id'];
            $wishlist->content_id = $contentId;
            $wishlist->app_id = $appId;
            
            $wishlist->save();
            return $this->sendResponse($wishlist, 'Daftar keinginan berhasil ditambahkan.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }

    public function removeWishlist($id)
    {
        try {
            $wishlist = Wishlist::find($id);
            if(is_null($wishlist)) {
                return $this->sendError('Barang keranjang tidak ditemukan');
            }

            $wishlist->delete();
            return $this->sendResponse([], 'Hapus item daftar keinginan dengan sukses.');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }   
    }
}
