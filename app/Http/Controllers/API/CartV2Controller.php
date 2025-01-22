<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CartV2;
use App\Models\DetailEvent;
use App\Models\Event;
use App\Models\MappingInstructor;
use App\Models\MembershipDuration;
use App\Models\OnlineCourse;
use App\Models\Transaction;
use App\Models\MasterTransactionFee;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WishlistV2;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CartV2Controller extends BaseController
{
    public function index()
    {
        $carts = CartV2::select([ 'id', 'qty', 'type', 'content_id' ])
            ->where('user_id','=',Auth::id())
            ->orderBy('created_at', 'DESC')->get()->toArray();

        $totalPrice = 0;
        $adminFee = 0;
        $carts = array_map( function($value) use(&$totalPrice,&$adminFee) {
            if($value['type'] == 'online-course') {
                // online course
                // dd('online-course');
                $select = [
                    'online_course.id AS online_course_id',
                    'online_course.image',
                    'online_course.title',
                    'online_course.description',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS author"),
                    DB::raw('coalesce(CAST(AVG(rating.rate) AS INT),0) AS rate'),
                    DB::raw('COUNT(rating.id) AS rating_count'),
                    'online_course.price',
                    'online_course.promo_price',
                    'online_course.video_length AS duration',
                ];

                $onlineCourse = OnlineCourse::select($select)
                    ->leftJoin('users', 'online_course.created_by','=', 'users.id')
                    ->leftjoin('rating', 'online_course.id','=', 'rating.online_course_id')
                    ->where('online_course.id','=',$value['content_id'])
                    ->where('online_course.status','=','publish')
                    ->groupBy([
                        'online_course.title',
                        'online_course.id',
                        'online_course.image',
                        'online_course.price',
                        'users.first_name',
                        'users.last_name',
                    ])->first();

                if($onlineCourse) {
		    $biayaAdmin = MasterTransactionFee::first();
		    //$event["fee"] = (int)$biayaAdmin->fee;
		    //$adminFee = $event["fee"];
		    $adminFee = (int)$biayaAdmin->fee;

                    $onlineCourse->description = Helper::shortDescription($onlineCourse->description);
                    $onlineCourse->duration = Helper::formatDuration( Helper::getDuration($onlineCourse->duration) );

                    $selectInstructor = [ 'instructor.id','instructor.name','instructor.title','instructor.description','instructor.image'];
                    $instructor = MappingInstructor::select($selectInstructor)
                        ->join('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id')
                        ->where('mapping_instructor.online_course_id', $value['content_id'])->first()->toArray();

                    $onlineCourse->instructor = $instructor;
                }

                $value['data'] = $onlineCourse !== null ? $onlineCourse->toArray() : [];
                // dd($onlineCourse);

                $totalPrice += $onlineCourse?->promo_price ?? $onlineCourse?->price ?? 0;

            } else if($value['type'] == 'event') {
                // event
                // dd('event');
                $select = [
                    'event.id AS event_id',
                    'event.type',
                    'detail_event.id AS detail_event_id',
                    'event.thumbnail_image',
                    'event.cover_image',
                    'event.master_category_id',
                    'event.master_subcategory_id',
                    'master_category.category_name',
                    'master_subcategory.subcategory_name',
                    'master_category.alias AS category_name_alias',
                    'master_subcategory.alias AS subcategory_name_alias',
                    'detail_event.description',
                    'detail_event.price',
                    'detail_event.title',
                    'detail_event.quota',
                    'detail_event.limit',
                    'detail_event.qty_include',
                    'event.title AS event_title',
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
                    DB::raw('COUNT(rating.id) AS rating_count'),
                    'detail_event.ticket_pass',
                    'detail_event.ticket_pass_id',
                ];

                $event = Event::select($select)
                    ->join('detail_event', 'event.id','=', 'detail_event.event_id')
                    ->join('master_category', 'event.master_category_id','=', 'master_category.id')
                    ->join('master_app', 'event.master_app_id','=', 'master_app.id')
                    ->leftjoin('rating', 'event.id','=', 'rating.event_id')
                    ->leftjoin('master_subcategory', 'event.master_subcategory_id','=', 'master_subcategory.id')
                    ->where('detail_event.id','=',$value['content_id'])
                    ->where('event.status','=','publish')
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
                    if($event->price != 0){
                        $biayaAdmin = MasterTransactionFee::first();
                        $event = $event->toArray();
                        $event["fee"] = (int)$biayaAdmin->fee;
                    }else{
                        $event["fee"] = 0;
                    }
		            $adminFee = $event["fee"];

                    if($event['ticket_pass']) {
                        $ticketPassId = json_decode($event['ticket_pass_id'], true);
                        $detailEvent = DetailEvent::select('date')->whereIn('id', $ticketPassId)
                            ->orderBy('date', 'ASC')->first()->toArray();

                        $event['date'] = $detailEvent['date'];
                    }

                    unset($event['ticket_pass']);
                    unset($event['ticket_pass_id']);

                    $value['data'] = $event;
                    $price = $event['promo_price'] > 0 ?  $event['price'] - $event['promo_price'] : $event['price'];
                    $totalPrice += $price * $value['qty'];
                }
            }

            return $value;
        }, $carts);
	    $totalPrice += $adminFee;
        $carts = array_filter($carts, function ($item) {
            return isset($item['data']) && !empty($item['data']);
        });
        // dd($carts);
        // $ngetest = [];
        // foreach ($carts as $key => $value) {
        //     // dd($value["data"]);
        //     // foreach ($value as $keyy => $valuee) {
        //     //     dd($valuee);
        //         if (!$value["data"] || $value["data"] == undifined) {
        //             array_push($ngetest, "ya");
        //         }
        //     // }
        // }
        $carts = array_values($carts);
        // dd($carts);
        $result = ['total_price' => $totalPrice, 'admin_fee' => $adminFee ,'items' => $carts];
        // dd($result, $totalPrice, $adminFee, $carts);
        return $this->sendResponse(result: $result, message: 'Daftar Keranjang');
    }

    public function store(Request $request)
    {
        try {
	    Log::error('content_id : '.$request->content_id);
            Log::error('type : '.$request->type);
            $validator = Validator::make(request()->all(), [
                'type' => 'required|in:event,online-course',
                'content_id' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            } else if(!empty($request->qty) && !is_int($request->qty)) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: ['qty' => ['Qty must be an integer']], code: 400);
            }

            $user = $request->get('session_user');
            $qty = $request->qty ?? 1;
            if($request->type == 'online-course') {
                OnlineCourse::findOrFail($request->content_id);
                $checkTransactionBook = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->where('transaction_detail.online_course_id', $request->content_id)
                    ->whereIn('transaction.payment_progress', ['booking'])
                    ->where('transaction.user_id', $user['id'])->count('transaction_detail.id');
                if($checkTransactionBook > 0){
                    return $this->sendError(error: 'Selesaikan pembayaran terlebih dahulu', code: 400);
                }

                $checkTransactionPurchased = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')

                    ->where('transaction_detail.online_course_id', $request->content_id)
                    ->whereIn('transaction.payment_progress', ['success'])
                    ->where('transaction.user_id', $user['id'])->count('transaction_detail.id');
                if($checkTransactionPurchased > 0){
                    return $this->sendError(error: 'Produk dibeli', code: 400);
                }

            } else if($request->type == 'membership') {
                MembershipDuration::findOrFail($request->content_id);
                $checkTransactionBook = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->where('transaction_detail.membership_duration_id', $request->content_id)
                    ->whereIn('transaction.payment_progress', ['booking'])
                    ->where('transaction.user_id', $user['id'])->count('transaction_detail.id');

                if($checkTransactionBook>0){
                    return $this->sendError(error: 'Selesaikan pembayaran terlebih dahulu', code: 400);
                } else if( User::isMembership($user['membership_plan_id']) ) {
                    return $this->sendError(error: 'Anda masih memiliki langganan keanggotaan', code: 400);
                }

            } else {
                $product = DetailEvent::findOrFail($request->content_id);
                $product = $product->toArray();

                $qtyCheck = $qty * $product['qty_include'];
                $check = TransactionController::validateEventQty($product, $qtyCheck);
                // dd($check);

                if(!$check['s']) {
                    return $this->sendError(error: $check['m'], code: 400);
                }
            }

            $qty = $request->type == 'event' ? $qty : 1;
            $checkCart = CartV2::where('user_id', $user['id'])
                ->where('content_id', $request->content_id)->first();

            if(is_object($checkCart)) {
                if($request->type == 'membership') {
                    return $this->SendError('Tindakan tidak diizinkan.', code: 400);
                }

                $checkCart->qty = $checkCart->qty + $qty;
                $checkCart->save();

                return $this->sendResponse($checkCart, 'Keranjang berhasil ditambahkan.');
            }else{
		$insertData = [
                	'user_id' => $user['id'],
                	'qty' => $qty,
                	'type' => $request->type,
                	'content_id' => $request->content_id,
            	];

            	CartV2::create($insertData);
	    }

            return $this->sendResponse($insertData, 'Keranjang berhasil ditambahkan.');

        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Barang Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function update($id, Request $request)
    {
        try {
            $validator = Validator::make(request()->all(), [
                'qty' => 'required|integer',
            ]);
            if ($validator->fails()) {
                return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
            }

            $user = $request->get('session_user');
            $cart = CartV2::findOrFail($id);

            if($cart->user_id != $user['id']) {
                return $this->SendError('Tidak sah.', code: 401);
            } else if($cart->type == 'membership') {
                return $this->SendError('Tindakan tidak diizinkan.', code: 400);
            }

            $qty = $request->type == 'event' ? ($request->qty ?? 1) : 1;
            $cart->qty = $qty;
            $cart->save();

            return $this->sendResponse($cart, 'Keranjang berhasil diperbarui.');

        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function moveWishlist($id_cart, $type, $id_event, Request $request)
    {
        // dd($id_cart, $type, $id_event);
        try {
            $user = $request->get('session_user');
            if ( $type != "event") {
                $cart = CartV2::findOrFail($id_cart);
                    
                if($cart->user_id != $user['id']) {
                    return $this->SendError('Tidak sah.', code: 401);
                } else if($cart->type == 'membership') {
                    return $this->SendError('Tindakan tidak diizinkan.', code: 400);
                }

                $wishlist = WishlistV2::create([
                    'user_id' => $user['id'],
                    'type' => $cart->type,
                    'content_id' => $cart->content_id,
                ]);
                
                $cart->delete();
            } else {
                $cart = CartV2::findOrFail($id_cart);
                    
                // if($cart->user_id != $user['id']) {
                //     return $this->SendError('Tidak sah.', code: 401);
                // } else if($cart->type == 'membership') {
                //     return $this->SendError('Tindakan tidak diizinkan.', code: 400);
                // }

                $wishlist = WishlistV2::create([
                    'user_id' => $user['id'],
                    'type' => $type,
                    'content_id' => $id_event,
                ]);
                
                $cart->delete();
            }
            return $this->sendResponse($wishlist, 'Keranjang berhasil dipindahkan.');

        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $user = $request->get('session_user');
            $cart = CartV2::findOrFail($id);
            if($cart->user_id != $user['id']) {
                return $this->SendError('Tidak sah.', code: 401);
            }

            $cart->delete();
            return $this->sendResponse([], 'Hapus item keranjang dengan sukses.');

        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function checkVoucher(Request $request)
    {
        $user = $request->get('session_user');
        $voucherNumber = $request->get('voucher_number');
        $biayaAdmin = MasterTransactionFee::first();
		$adminFee = (int)$biayaAdmin->fee;
        
        // dd($voucherNumber);
        $today = date('Y-m-d');

        if(!$voucherNumber) {
            return $this->sendError('Nomor voucher diperlukan', code: 400);
        }

        $cartItems = CartV2::where('user_id', $user['id'])->get();
        // dd($cartItems);
        if(count($cartItems) == 0) {
            return $this->sendError('Keranjang Anda kosong.', code: 400);
        }

        $totalPrice = 0;
        foreach($cartItems as $value) {
            if($value['type'] == 'online-course') {
                // online course
                $onlineCourse = OnlineCourse::select(['online_course.price', 'online_course.promo_price'])
                    ->where('online_course.id','=',$value['content_id'])
                    ->where('online_course.status','=','publish')->first();

                if(!$onlineCourse) continue;
                $totalPrice += ($onlineCourse->price - $onlineCourse->promo_price);
                // dd($totalPrice);
            } else {
                // event
                $event = Event::select(['detail_event.price', 'detail_event.promo_price'])
                    ->join('detail_event', 'event.id','=', 'detail_event.event_id')
                    ->where('detail_event.id','=', $value['content_id'])
                    ->where('event.status','=','publish')->first();
                // dd($event);
                if(!$event) continue;
                $price = ($event->price - $event->promo_price);
                $totalPrice += $price * $value['qty'];
            }
        }
        $totalPrice = $totalPrice + $adminFee;
        // dd($totalPrice);

        $checkVoucher = TransactionController::voucherAvailable($voucherNumber, $today);
        
        if (!$checkVoucher) {
            return $this->sendError(error: 'Voucher tidak tersedia');
        }

        $checkVoucherByUser = Transaction::select('id')->where('voucher_number', $voucherNumber)
            ->where('user_id', $user['id'])->count(['id']);
        
        if ($checkVoucherByUser >= $checkVoucher['limit']) {
            return $this->sendError(error: 'Voucher sudah digunakan', code: 400);
        }

        $checkVoucherTransaction = Transaction::select('id')->where('voucher_number', $voucherNumber)->count(['id']);
        
        if ($checkVoucherTransaction > $checkVoucher['qty']) {
            return $this->sendError(error: 'Voucher habis', code: 400);
        }

        $checkVoucher['voucher_number'] = $voucherNumber;
        // dd($checkVoucher, $totalPrice);
        $calculateDiscount = TransactionController::calculateDiscount($checkVoucher, $totalPrice);
        // dd($calculateDiscount, $checkVoucher, $totalPrice);
        $amount = $calculateDiscount['amount'];
        $discount = $calculateDiscount['discount'];

        $result = [
            'total_price' => $totalPrice,
            'discount' => $discount,
            'discount_type' => $checkVoucher['type'],
            'total_amount' => $amount
        ];

        return $this->sendResponse($result, 'Cek Voucher Berhasil.');
    }
}
