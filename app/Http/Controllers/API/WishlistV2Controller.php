<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailEvent;
use App\Models\Event;
use App\Models\OnlineCourse;
use App\Models\WishlistV2;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WishlistV2Controller extends BaseController
{
    protected $prefix;

    public function __construct()
    {
        $this->prefix = 'wishlist_' . Auth::id();
    }
    
    public function index()
    {
        $wishlist = WishlistV2::select([ 'id', 'type', 'content_id' ])
            ->where('user_id','=',Auth::id())
            ->orderBy('created_at', 'DESC')->get()->toArray();

        $wishlist = array_map( function($value) {
            if($value['type'] == 'online-course') {
                // online course
                $select = [
                    'online_course.id',
                    'online_course.image as thumbnail_image',
                    'online_course.title',
                    'online_course.type',
                    'online_course.meta_title',
                    'online_course.meta_description',
                    'online_course.meta_keyword',
                    'online_course.status',
                    DB::raw('CONCAT(users.first_name, \' \', users.last_name) AS author'),
                    'online_course.voucher',
                    'online_course.price',
                    'online_course.promo_price',
                    DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate'),
                    DB::raw('COUNT(rating.id) AS rating_count'),
                    'online_course.description',
                    'online_course.video_length AS duration',
                ];
              
                $onlineCourse = OnlineCourse::select($select)
                    ->join('users', 'online_course.created_by','=', 'users.id')
                    ->leftjoin('rating', 'online_course.id','=', 'rating.online_course_id')
                    ->where('online_course.id','=',$value['content_id'])
                    ->where('online_course.status','=','publish')
                    ->groupBy([
                        'online_course.id',
                        'users.first_name',
                        'users.last_name'
                    ])->first();

                $value['data'] = $onlineCourse !== null ? $onlineCourse->toArray() : [];
                $value['data']['description'] = Helper::shortDescription($value['data']['description']);
                $value['data']['duration'] = Helper::formatDuration( Helper::getDuration($value['data']['duration']) );
                 
            } else {
                // event
                $select = [
                    'event.id',
                    'event.title',
                    'event.type',
                    'event.thumbnail_image',
                    'event.cover_image',
                    'event.master_category_id',
                    'event.master_subcategory_id',
                    'master_category.category_name',
                    'master_subcategory.subcategory_name',
                    'master_category.alias AS category_name_alias',
                    'master_subcategory.alias AS subcategory_name_alias',
                    'event.content as description',
                    'event.province',
                    'event.city',
                    'event.google_location',
                    'event.place',
                    'event.address',
                    'event.view',
                    'detail_event.price',
                    'detail_event.promo_price',
                    'master_app.app_name',
                    DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate')
                ];
                
                $event = Event::select($select)
                ->join('detail_event', 'event.id','=', 'detail_event.event_id')
                ->join('master_category', 'event.master_category_id','=', 'master_category.id')
                ->join('master_app', 'event.master_app_id','=', 'master_app.id')
                ->leftjoin('rating', 'event.id','=', 'rating.event_id')
                ->leftjoin('master_subcategory', 'event.master_subcategory_id','=', 'master_subcategory.id')
                ->where('event.id','=',$value['content_id'])
                ->where('event.status','=','publish')
                ->orderBy('detail_event.price', 'ASC')
                ->groupBy([
                    'detail_event.id',
                    'event.master_category_id',
                    'event.master_subcategory_id',
                    'master_category.category_name',
                    'master_subcategory.subcategory_name',
                    'master_category.alias',
                        'master_subcategory.alias',
                        'event.id',
                        'master_app.app_name'
                        ])->first();

                if($event) {
                    $event = $event->toArray();
                    $arrDescription = json_decode($event['description'], true);
                    $event['description'] = $arrDescription[0]['content'] ?? '';
                    $value['data'] = $event;

                } else {
                    $value['data'] =  [];
                }
            }
            return $value;
        }, $wishlist);

        return $this->sendResponse(result: $wishlist, message: 'Daftar Keinginan');
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make(request()->all(), [
                'type' => 'required|in:event,online-course',
                'content_id' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $user = $request->get('session_user');
            if($request->type == 'online-course') {
                OnlineCourse::findOrFail($request->content_id);
            } else {
                Event::findOrFail($request->content_id);
            }

            $checkWishlist = WishlistV2::where('user_id', $user['id'])
                ->where('content_id', $request->content_id)->first();
            if(is_object($checkWishlist)) {
                return $this->sendError(error: 'Barang sudah ditambahkan ke daftar keinginan.', code: 400);
            }

            $insertData = [
                'user_id' => $user['id'],
                'type' => $request->type,
                'content_id' => $request->content_id,
            ];

            WishlistV2::create($insertData);
            return $this->sendResponse($insertData, 'Daftar keinginan berhasil ditambahkan.');

        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function destroy($id)
    {
        try {
            $wishlist = WishlistV2::findOrFail($id);
            $wishlist->delete();

            return $this->sendResponse([], 'Hapus item daftar keinginan dengan sukses.');
        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }
}