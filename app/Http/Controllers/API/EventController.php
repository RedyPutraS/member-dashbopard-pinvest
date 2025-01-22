<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\DetailEvent;
use App\Models\Event;
use App\Models\MappingInstructor;

use App\Models\Rating;
// use App\Models\Instructor;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WishlistV2;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class EventController extends BaseController
{
    protected $prefix;
    public function __construct()
    {
        $this->prefix = 'event';
    }

    private function checkAddedToWishlist(Request $request, $id)
    {
        $user = $request->get('session_user');
        if(!$user) return false;
        return WishlistV2::where('user_id', $user['id'])->where('content_id', $id)->count() > 0;
    }

    /**
     * @OA\Get(
     *     path="/api/pilearning/event/?limit=6&start=0&category=webinar",
     *     tags={"PiLearning"},
     *     @OA\Response(response="200", description="Display a listing of webinar.")
     * )
     * @OA\Get(
     *     path="/api/pilearning/event/?limit=6&start=0&category=seminar",
     *     tags={"PiLearning"},
     *     @OA\Response(response="200", description="Display a listing of webinar.")
     * )
     * @OA\Get(
     *     path="/api/pilearning/event/?limit=6&start=0&category=workshop",
     *     tags={"PiLearning"},
     *     @OA\Response(response="200", description="Display a listing of workshop.")
     * )
     *
     * @OA\Get(
     *     path="/api/picircle/directory",
     *     tags={"PiCircle"},
     *     @OA\Response(response="200", description="Display a listing of directory.")
     * )
     * @OA\Get(
     *     path="/api/picircle/forum",
     *     tags={"PiCircle"},
     *     @OA\Response(response="200", description="Display a listing of forum.")
     * )
     */
    public function index(Request $request, $app, $rawResponse = false)
    {
        // return 
        // return response()->json("TEST");
        
        $message = 'Daftar Acara';
        $time = date('Y-m-d');
        $prefix = $this->prefix . '_' . $app;
        $rangeTime = $request->input('time');
        // dd($rangeTime);
        if ($rangeTime) {
            $prefix .= '_' . $rangeTime;
        }
        $category = $request->input('category');
        $search = $request->input('search');
        $filter = $request->input('filter');
        $sort = $request->input('sort');
        $price = $request->input('price');
        $rating = $request->input('rating');
        $type = $request->input('type');
        $subcategory = $request->input('subcategory');
        if ($search) {
            $prefix .= '_' . 'search_' . $search;
        }
        if ($filter) {
            $prefix .= '_' . 'filter_' . $filter;
        }
        if ($category) {
            $prefix .= '_' . 'category_' . $category;
        }
        if ($price) {
            $prefix .= '_' . 'price_' . $price;
        }
        if ($rating) {
            $prefix .= '_' . 'rating_' . $rating;
        }
        if ($type) {
            $prefix .= '_' . 'type_' . $type;
        }
        if ($subcategory) {
            $prefix .= '_' . 'subcategory_' . $subcategory;
        }
        if ($rangeTime == 'week') {
            $start_time = date('D') != 'Sun' ? date('Y-m-d', strtotime('+1 days')) : $time;
            
            
            $end_time = date("Y-m-d", strtotime('sunday this week'));
            $prefix .= '_' . $start_time . '_' . $end_time;
        } else {
            $start_time = null;
            $end_time = null;
        }
        

        $limit = $request->input('limit', 12);
        // dd($limit);
        $limit = $limit > 100 ? 100 : (int)$limit;

        // $prefix .= '_' . $start . '_' . $limit;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

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
            'detail_event.created_at',
            DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate')
        ];

        $subQuery = "(SELECT DISTINCT ON (event_id)
            id,
            event_id,
            date,
            title,
            ticket_pass_id,
            ticket_pass,
            start_time,
            end_time,
            price,
            promo_price,
            status,
            created_at
            FROM detail_event";

        if ($rangeTime === 'day') {
            $subQuery .= " WHERE date = '$time'";
        } else if ($rangeTime === 'week') {
            $subQuery .= " WHERE date >= '$start_time' AND date <= '$end_time'";
        }else{
            $subQuery .= " WHERE date >= '$time'";
        }

        $subQuery .= " ORDER BY event_id,id,date,title)detail_event";

        $listEvent = Event::join(DB::raw($subQuery),
            function ($join)
            {
                $join->on('event.id', '=', 'detail_event.event_id');
            })
            ->leftjoin('master_category', 'event.master_category_id', 'master_category.id')
            ->join('master_app', 'event.master_app_id', '=', 'master_app.id')
            ->leftjoin('master_subcategory', 'event.master_subcategory_id', '=', 'master_subcategory.id')
            ->leftjoin('rating', 'event.id', '=', 'rating.event_id')
            ->select($select);


        if (!empty($search)) {
            $keywordIsApp = SearchController::keywordIsApp($search);
            if(!$keywordIsApp || $app != $search) {
                $listEvent = $listEvent->where(DB::raw('LOWER(event.title)'), 'LIKE', '%' . strtolower(trim($search)) . '%')
                    ->orWhere(DB::raw('LOWER(master_category.category_name)'), 'LIKE', '%' . strtolower(trim($search)) . '%')
                    ->orWhere(DB::raw('LOWER(master_subcategory.subcategory_name)'), 'LIKE', '%' . strtolower(trim($search)) . '%');
            }
        }

        if (!empty($sort)) {
            if ($sort == 'title-asc') {
                $listEvent = $listEvent->orderBy('event.title', 'ASC');
            } elseif ($sort == 'title-desc') {
                $listEvent = $listEvent->orderBy('event.title', 'DESC');
            } elseif ($sort == 'popular') {
                $listEvent = $listEvent->orderBy('event.view', 'DESC');
            } else {
                $listEvent = $listEvent->orderBy('detail_event.id', 'DESC');
            }
        }

        if(!empty($filter)) {
            $arrFilter = json_decode(rawurldecode($filter), true);

            if (isset($arrFilter['rating']) && !is_null($arrFilter['rating'])) {
                $listEvent = $listEvent->having(DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0)'), '>=', $arrFilter['rating']);
            }

            if(!empty($arrFilter['price'])) {
                if ($arrFilter['price'] == 'free') {
                    $listEvent = $listEvent->where('detail_event.price', '=', 0);
                } elseif ($arrFilter['price'] == 'paid') {
                    $listEvent = $listEvent->where('detail_event.price', '>', 0);
                }
            }

            if(!empty($arrFilter['type'])) {
                $listEvent = $listEvent->where('event.type', $arrFilter['type']);
            }
        }

        if (!empty($category)) {
            $listEvent = $listEvent->where('master_category.alias', '=', $category);
        }

        if (!empty($subcategory)) {
            $listEvent = $listEvent->where('master_subcategory.alias', '=', $subcategory);
        }

        if (!empty($type)) {
            $listEvent = $listEvent->where('event.type', 'LIKE', '%'.$type.'%');
            // dd($listEvent);
        }


        $listEvent = $listEvent->where('master_app.alias', '=', $app);
        $listEvent = $listEvent->limit($limit);
        
        
        $groupBy = [
            'detail_event.id',
            'detail_event.created_at',
            'event.title',
            'detail_event.price',
            'detail_event.promo_price',
            'event.type',
            'event.master_category_id',
            'event.master_subcategory_id',
            'master_category.category_name',
            'master_subcategory.subcategory_name',
            'master_category.alias',
            'master_subcategory.alias',
            'event.id',
            'master_app.app_name'
        ];
        $listEvent = $listEvent->groupBy($groupBy);
        $listEvent = $listEvent->where('event.status', 'publish');
        $listEvent = $listEvent->orderBy('event.id', 'ASC')->paginate($limit)->toArray();
        // dd($listEvent);
        
        $resultEvent = [];
        foreach ($listEvent['data'] as $key => $value) {
            $hour = 0;
            $minute = 0;
            $duration = '';
            $count_minute = 0;
            $now = date('Y-m-d H:i:s');
            $today = now()->format('Y-m-d'); // Mendefinisikan $today di luar query

            $detailEvent = DetailEvent::where('event_id', $value['id'])
                ->where('status', 'publish')
                ->whereNotNull('date')
                ->where(function ($query) use ($rangeTime, $today) { // Gunakan use($today)
                    if ($rangeTime === 'day') {
                        // Filter untuk event hari ini dengan waktu yang valid
                        $query->where('date', $today)
                            ->whereTime('start_time', '<=', now()->format('H:i:s'))
                            ->whereTime('end_time', '>=', now()->format('H:i:s'));
                    } elseif ($rangeTime === 'week') {
                        // Ambil data untuk minggu ini dengan filter jam akhir
                        $query->whereBetween('date', [
                                date('Y-m-d', strtotime('monday this week')), // Awal minggu (Senin)
                                date('Y-m-d', strtotime('sunday this week'))  // Akhir minggu (Minggu)
                            ])
                            ->where(function ($query) use ($today) { // Tambahkan use($today)
                                $query->where('date', '>', $today) // Untuk tanggal di masa depan
                                    ->orWhere(function ($query) use ($today) { // Tambahkan use($today)
                                        $query->where('date', $today) // Untuk tanggal hari ini
                                            ->whereTime('end_time', '>=', now()->format('H:i:s')); // Jam belum melewati end_time
                                    });
                            });
                    } else {
                        // Ambil semua tanggal dari sekarang, tetapi pastikan tidak melewati end_time untuk hari ini
                        $query->where('date', '>=', $today)
                            ->where(function ($query) use ($today) { // Tambahkan use($today)
                                $query->where('date', '>', $today) // Untuk tanggal di masa depan
                                    ->orWhere(function ($query) use ($today) { // Tambahkan use($today)
                                        $query->where('date', $today) // Untuk tanggal hari ini
                                            ->whereTime('end_time', '>=', now()->format('H:i:s')); // Jam belum melewati end_time
                                    });
                            });
                    }
                })
                ->orderBy('date', 'ASC')
                ->get()
                ->toArray();

            if(count($detailEvent) == 0) {
                continue;
            }
            $ticketPassId = [];
            $price = [];
            $allprice = 
            $date = [];
	        $membatasi = [];

            foreach ($detailEvent as $to => $items) {
                $ticketPassId[] = $items['id'];
                $price[] = $items['price'];
		        $membatasi[] = $items['limit'];
                $extract_date = $items['date'] . ' ' . $items['start_time'];
                $extract_end_date = $items['date'] . ' ' . $items['end_time'];
                // dd($extract_date, $extract_end_date);
                $from_time = strtotime($extract_date);
                $to_time = strtotime($extract_end_date);
                $count_minute += round(abs($to_time - $from_time) / 60, 2);

                if(!in_array($items['date'], $date)) {
                    $date[] = $items['date'];
                }
            }
            // return response()->json($price);

            sort($price);
            $hour = floor($count_minute / 60);
            $minute = $count_minute - ($hour * 60);
            if ($hour) {
                $duration .= $hour . ' Jam ';
            }
            if ($minute) {
                $duration .= $minute . ' Menit';
            }

            if(!$rawResponse) unset($value['created_at']);

            $value['rate'] = round($value['rate'], 1);
            $value['price'] = $price[0] ?? 0;
            $value['ticket']['date'] = $date;
            $value['ticket']['price'] = $price;
            $value['ticket']['duration'] = $duration;
	        $value['ticket']['limit'] = $membatasi[0] ?? 0;

            $arrDescription = json_decode($value['description'], true);
            $value['description'] = $arrDescription[0]['content'] ?? '';

            $value['added_to_wishlist'] = $this->checkAddedToWishlist($request, $value['id']);
            unset($listEvent['data'][$key]['ticket_pass_id']);
            $resultEvent[] = $value;
        }

        if($rawResponse) return $resultEvent;
        $pagination = Helper::getPaginationData($listEvent, $limit);
        // Helper::setRedis($prefix, json_encode($listEvent), 500);
        return $this->sendResponse(result: $resultEvent, message: $message, pagination: $pagination);
    }

    public function detail($app, $id, Request $request)
    {
        //try {
            $event = Event::findOrFail($id);
            $today = date('Y-m-d');

            $message = 'Detail Acara ' . $id;
            $prefix = $this->prefix . '_detail_' . $app . '_' . $id;
            // $checkRedis = Helper::getRedis($prefix);
            // $getRedis = $checkRedis ? json_decode($checkRedis) : false;
            // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }

            $event->view += 1;
            $event->save();

            $select = [
                'event.id',
                'event.title',
                'event.type',
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
                'detail_event.price',
                'detail_event.promo_price',
                'master_app.app_name',
                'event.instructor'
            ];
            $event = Event::select($select)
                ->join('detail_event', 'event.id', '=', 'detail_event.event_id')
                ->leftjoin('master_category', 'event.master_category_id', '=', 'master_category.id')
                ->join('master_app', 'event.master_app_id', '=', 'master_app.id')
                ->leftjoin('master_subcategory', 'event.master_subcategory_id', '=', 'master_subcategory.id')
                ->where('event.id', '=', $id);
            
            $event = $event->where('master_app.alias', '=', $app);
            $event = $event->where('event.status', 'publish')->firstOrFail()->toArray();
            $instructor = $event['instructor'];
            // dd($instructor);
            if ($instructor != null) {
                $instructor = DB::table('instructor')->where('id', $instructor)->first();
            } else {
                $instructor = null;
            }
            // dd($event['instructor']);

            $event['description'] = json_decode($event['description'], true);
            $event['description'] = Helper::arrayReplaceKey($event['description'], 'content', 'description');

            $selectDetail = [
                'id',
                'title',
                'description',
                'detail_event.date',
                'limit',
                'qty_include',
                'ticket_pass',
                'ticket_pass_id',
                'date',
                'start_time',
                'end_time',
                'price',
                'quota'
            ];
            $detailEvent = DetailEvent::select($selectDetail)
                ->where('event_id', $id)
                ->where('status', 'publish')
                ->where(function ($query) {
                    $query->where('ticket_pass', true)
                        ->orWhere(function ($query) {
                            $query->where('ticket_pass', false)
                                ->where(function ($subQuery) {
                                    $subQuery->where('date', '>=', DB::raw('CURRENT_DATE')) // Tanggal lebih dari hari ini
                                        ->orWhere(function ($subQuery) {
                                            $subQuery->where('date', '=', DB::raw('CURRENT_DATE')) // Tanggal sama dengan hari ini
                                                ->where('start_time', '>=', date('H:i:s')); // Waktu lebih dari sekarang
                                        });
                                });
                        });
                })
                ->orderBy('date', 'ASC')
                ->get()
                ->toArray();
            // dd($detailEvent, $id);

            $count_all_minute = 0;
            $item = 0;

            foreach($detailEvent as $value){
                $isEnded = strtotime(date('Y-m-d')) >= strtotime($value['date']);
                // dd($isEnded);
                if($value['ticket_pass']){
                    if(!$value['ticket_pass'] && $isEnded) continue;
                    $hour = 0;
                    $minute = 0;
                    $duration = '';
                    $count_minute = 0;
                    $ticketPass = json_decode($value['ticket_pass_id'],true);
                    $dateTicketPass = DetailEvent::select('id','date','start_time','end_time')
                        ->whereIn('id', $ticketPass)->orderBy('date', 'ASC')
                        ->get()->toArray();

                    $ticketPassId = [];
                    foreach ($dateTicketPass as $to => $items) {
                        $ticketPassId[] = $items['id'];
                        $extract_date = $items['date'] . ' ' . $items['start_time'];
                        $extract_end_date = $items['date'] . ' ' . $items['end_time'];
                        $from_time = strtotime($extract_date);
                        $to_time = strtotime($extract_end_date);
                        $count_minute += round(abs($to_time - $from_time) / 60, 2);
                        $count_all_minute += $count_minute;
                    }
                    $hour = floor($count_minute / 60);
                    $minute = $count_minute - ($hour * 60);
                    if ($hour) {
                        $duration .= $hour . ' Jam ';
                    }
                    if ($minute) {
                        $duration .= $minute . ' Menit';
                    }
                    $detailEventId = $value['id'];
                    $subQuery = "( SELECT DISTINCT ON ( transaction_id ) transaction_id, SUM ( qty ) AS total FROM transaction_detail WHERE detail_event_id = $detailEventId GROUP BY ID)transaction_detail";
                    $getQuota = Transaction::join(DB::raw($subQuery),
                        function ($join)
                        {
                            $join->on('transaction.id', '=', 'transaction_detail.transaction_id');
                        })
                        ->whereIn('transaction.payment_progress', ['booking','success'])->sum('transaction_detail.total');

                    $event['ticket'][$item] = $value;
                    $event['ticket'][$item]['quota_used'] = (int) $getQuota;
                    $event['ticket'][$item]['quota_available'] = $value['quota'] - (int) $getQuota;
                    $event['ticket'][$item]['duration'] = $duration;
                    $event['ticket'][$item]['date'] = $dateTicketPass[0]['date'];
                    $event['ticket'][$item]['start_time'] = $dateTicketPass[0]['start_time'];
                    $event['ticket'][$item]['end_time'] = $dateTicketPass[0]['end_time'];
                    $event['ticket'][$item]['ticket_pass_id'] = json_decode($event['ticket'][$item]['ticket_pass_id'], true);

                }else{
                    $hour = 0;
                    $minute = 0;
                    $duration = '';
                    $count_minute = 0;
                    $date = [];
                    foreach ($detailEvent as $to => $items) {
                        // $ticketPassId[] = $items['id'];
                        // $price[] = $items['price'];
                        // $membatasi[] = $items['limit'];
                        $extract_date = $items['date'] . ' ' . $items['start_time'];
                        $extract_end_date = $items['date'] . ' ' . $items['end_time'];
                        
                        $from_time = strtotime($extract_date);
                        $to_time = strtotime($extract_end_date);
                        $count_minute += round(abs($to_time - $from_time) / 60, 2);
                        // $tot = $count_minute;
                        
                        if(!in_array($items['date'], $date)) {
                            $date[] = $items['date'];
                        }
                    }
                    $hour = floor($count_minute / 60);
                    $minute = $count_minute - ($hour * 60);
                    if ($hour) {
                        $duration .= $hour . ' Jam ';
                    }
                    if ($minute) {
                        $duration .= $minute . ' Menit';
                    }

                    $getQuota = Transaction::join('transaction_detail','transaction.id','=','transaction_detail.transaction_id' )
                        ->where('transaction_detail.detail_event_id', $value['id'])
                        ->whereIn('transaction.payment_progress', ['booking','success'])
                        ->sum('transaction_detail.qty');
                    $event['ticket'][$item] = $value;
                    $event['ticket'][$item]['quota'] = $value['quota'];
                    $event['ticket'][$item]['quota_used'] = (int) $getQuota;
                    $event['ticket'][$item]['quota_available'] = $value['quota']- (int) $getQuota;
                    $event['ticket'][$item]['duration'] = $duration;
                    $ticketPassId = [$value['id']];

                }

                unset($ticketPassId);
                $item++;
            }

            ReferralController::store($request,'event',$id);
            $duration_all = '';
            if($isEnded){
                $count_minute = 0;
            }
            $hour = floor($count_minute / 60);
            $minute = $count_minute - ($hour * 60);
            if ($hour) {
                $duration_all .= $hour . ' Jam ';
            }
            if ($minute) {
                $duration_all .= $minute . ' Menit';
            }

            $event['duration'] = $duration_all;
            $event['instructor'] = $instructor;
            $event['added_to_wishlist'] = $this->checkAddedToWishlist($request, $event['id']);
            // Helper::setRedis($prefix, json_encode($detailEvent), 500);
            return $this->sendResponse(result: $event, message: $message);

        //} catch(ModelNotFoundException $e){
            //return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        //} catch (\Exception $e) {
            //$err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            //return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        //}
    }

    public function detailWithRating($id)
    {
        $prefix = $this->prefix . '_detail_event_rating_' . '_' . $id;
        // $checkRedis = Helper::getRedis($prefix);
//        $getRedis = $checkRedis ? json_decode($checkRedis) : false;
//        if($getRedis){
//            return $getRedis;
//        }
        $select = [
            'event.thumbnail_image AS image',
            'detail_event.title',
            'event.title AS event_title',
            'detail_event.date',
            'detail_event.start_time',
            'detail_event.end_time',
            'detail_event.url_meeting',
            'event.content AS description',
            'detail_event.event_id',
        ];

        $detailEvent = DetailEvent::select($select)
            ->join('event', 'detail_event.event_id', '=', 'event.id')
            ->join('master_app', 'event.master_app_id', '=', 'master_app.id')
            ->where('detail_event.id', '=', $id)
            ->where('detail_event.status', 'publish')
            ->where('event.status', 'publish')->first();
        if(!$detailEvent) return null;

        $getRating = Rating::select([
            DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'),
            DB::raw('COUNT(rating.id) AS rating_count'),
        ])->where('event_id', $detailEvent->event_id)->first();

        $detailEvent->rate = round($getRating->rate, 1);
        $detailEvent->rating_count = $getRating->rating_count;
        unset($detailEvent->event_id);

        $detailEvent = $detailEvent->toArray();
        $arrDescription = json_decode($detailEvent['description'], true);
        $detailEvent['description'] = $arrDescription[0]['content'] ?? '';

        // Helper::setRedis($prefix,json_encode($detailEvent),500);
        return $detailEvent;
    }


}
