<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\DetailEvent;
use App\Models\MappingInstructor;
use App\Models\OnlineCourse;
use App\Models\Voucher;
use App\Models\TransactionDetail;
use App\Http\Controllers\API\EventController;
use DateTime;
use App\Http\Controllers\API\OnlineCourseController;
use App\Library\Helper;
use App\Mail\NotifMembershipMail;
use App\Mail\NotifTransaksiMail;
use App\Models\CartV2;
use App\Models\MembershipDuration;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\Event;
use App\Models\MasterTransactionFee;
use App\Models\MembershipExclusive;
use App\Models\MembershipVoucher;
use App\Models\ModulOnlineCourse;
use App\Models\Notification;
use App\Models\Rating;
use App\Models\TransactionFee;
use App\Models\TransactionTab;
use App\Models\TransactionTicketPass;
use App\Models\TransactionTicketQr;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use phpseclib3\Crypt\RC2;

class TransactionController extends BaseController
{
    protected $urlPayment;
    protected $ttlFiveDay;
    protected $va_code;
    protected $bank_image;
    protected $bank_fee;
    protected $payment_progress;
    protected $urlPaymentXendit;

    public function __construct()
    {
        $this->urlPayment = env('ESPAY_URL', 'https://sandbox-api.espay.id');

        $this->ttlFiveDay = 432000;
        $this->va_code = [
            '002',
            '008',
            '009',
            '011',
            '013',
            '014',
            '016',
            '022',
            '057'
        ];
        $this->bank_image = [
            '002' => 'https://primedge.oss-ap-southeast-5.aliyuncs.com/Bank/bri%201%20%281%29.png',
            '008' => 'https://primedge.oss-ap-southeast-5.aliyuncs.com/Bank/kisspng-bank-mandiri-logo-credit-card-portable-network-gra-go-to-image-page-5b636680e15f33.7180253715332409609231.jpg',
            '009' => 'https://kampus-kita.oss-ap-southeast-5.aliyuncs.com/public/asset/images/bni.png',
            '011' => 'https://primedge.oss-ap-southeast-5.aliyuncs.com/test/download%20%2814%29.png',
            '013' => 'https://primedge.oss-ap-southeast-5.aliyuncs.com/Bank/permata%201.png',
            '014' => 'https://kampus-kita.oss-ap-southeast-5.aliyuncs.com/public/asset/images/bca.png',
            '016' => 'https://kampus-kita.oss-ap-southeast-5.aliyuncs.com/public/asset/images/bii.jpg',
            '022' => 'https://primedge.oss-ap-southeast-5.aliyuncs.com/test/download%20%2813%29.png',
            '057' => 'https://kampus-kita.oss-ap-southeast-5.aliyuncs.com/',
        ];
        $this->bank_fee = [
            '002' => 4500,
            '008' => 3500,
            '009' => 0,
            '011' => 2000,
            '013' => 2000,
            '014' => 3850,
            '016' => 2000,
            '022' => 3000,
            '057' => 0,
        ];
        $this->payment_progress = [
            'S' => 'success',
            'F' => 'failed',
            'SP' => 'success',
            'IP' => 'booking',
            'EX' => 'expired'
        ];

    }

    public function index()
    {
        //
    }


    public static function validateEventQty($product, $qty)
    {
        if ($qty > $product['limit']) {
            return ['s' => false, 'm' => 'Jumlah maksimum'];
        }
        // dd($qty, $product['limit']);

        $user = request()->get('session_user');
        $checkQuotaByUser = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
            ->where('transaction_detail.detail_event_id', $product['id'])
            ->whereIn('transaction.payment_progress', ['success', 'booking'])
            ->where('transaction.user_id', $user['id'])->sum('transaction_detail.qty');
        if ($checkQuotaByUser >= $product['limit']) {
            return ['s' => false, 'm' => 'Maksimal jumlah pembelian tiket kuota user.'];
        }

        $checkQuota = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
            ->whereIn('transaction.payment_progress', ['success', 'booking'])
            ->where('transaction_detail.detail_event_id', $product['id'])
            ->sum('transaction_detail.qty');
        if ($checkQuota >= $product['quota']) {
            return ['s' => false, 'm' => 'Kuota habis'];
        }

        return ['s' => true];
    }

    public function listBank()
    {
        $prefix = 'list_bank_va';
        $message = 'Daftar Bank VA';
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $payload['key'] = env('ESPAY_MERCHANT_CODE');
        $response = Http::asForm()->timeout(20)->post($this->urlPayment . '/rest/merchant/merchantinfo', $payload);
        $result = json_decode($response->body(), true);

        $arrPanduanPg = file_get_contents( env('ESPAY_PANDUAN') );
        $arrPanduanPg = json_decode($arrPanduanPg, true);

        $listVa = [];
        foreach ($result['data'] as $key => $value) {
            if (in_array($value['bankCode'], $this->va_code)) {
                if (str_contains($value['productCode'], 'ATM')) {
                    $value['fee'] = $this->bank_fee[ $value['bankCode'] ] ?? '';
                    $value['image'] = $this->bank_image[ $value['bankCode'] ] ?? '';
                    $value['intruction'] = [];

                    foreach($arrPanduanPg as $val) {
                        if( preg_match("/{$val['bankName']}/i", $value['productName']) == 1 ) {
                            $value['intruction'] = $val['intruction'];
                            break;
                        }
                    }

                    $listVa[] = $value;
                }
            }
        }

        // Helper::setRedis($prefix, json_encode($listVa), 60 * 60 * 24);
        return $this->sendResponse(result: $listVa, message: $message);
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $bank_code = $request->get('bank_code');
            $listBank = self::listBank()->getOriginalContent()['data'];
            $arrBankCode = array_column($listBank, 'bankCode');
            $searchBank = array_search($bank_code, $arrBankCode);

            if ($searchBank === false) {
                return $this->sendError(error: 'Metode Pembayaran tidak diizinkan');
            }

            $bank = $listBank[ $searchBank ];
            $user = $request->get('session_user');
            $type = $request->get('type') ?? 'event';
            $app = $request->get('app');
            $id = $request->get('id');
            $voucher = $request->get('voucher');

            $prefix = 'PEVN';
            if ($type == 'online-course') {
                $prefix = 'POC';
            } else if($type == 'membership') {
                $prefix = 'PMEM';
            }

            $micro_date = microtime();
            $date_array = explode(" ", $micro_date);
            $date = date('Y/m/d');
            $microtime = str_replace(':', '', date('h:i:s')) . str_replace('.', '', $date_array[0]);
            $order_id = $prefix . '/' . $date . '/' . strtoupper(Str::random(6)) . $microtime;
            $rq_uuid = strtoupper(Str::random(6) . "-" . Str::random(13));
            $rq_datetime = Carbon::now()->format("Y-m-d H:m:s");
            $response = [
                'order_id' => $order_id,
                'date' => $rq_datetime
            ];
            $today = date('Y-m-d');
            $product = null;
            $qty = 1;
            $online = true;
            $user_id = $user['id'];
            $handphone = !empty($user['no_hp']) ? $user['no_hp'] : env('ESPAY_PHONE');

            if ($type == 'event') {
                $select = [
                    'detail_event.id',
                    'event.title',
                    'detail_event.price',
                    'detail_event.voucher',
                    'detail_event.qty_include',
                    'detail_event.limit',
                    'detail_event.quota',
                    'detail_event.voucher',
                    'detail_event.ticket_pass',
                    'detail_event.ticket_pass_id',
                    'event.master_app_id',
                    'event.master_category_id',
                    'event.type',
                    'master_category.category_name',
                ];
                $product = DetailEvent::select($select)
                    ->join('event', 'detail_event.event_id', '=', 'event.id')
                    ->join('master_category', 'master_category.id', '=', 'event.master_category_id')
                    ->where('event.status', 'publish')
                    ->whereDate('detail_event.date', '>=', $today)
                    ->where('detail_event.status', 'publish')
                    ->where('detail_event.id', $id)->first();
                if (!$product) {
                    return $this->sendError(error: 'Acara tidak ditemukan');
                }
                if($product['type']=='offline'){
                    $online=false;
                }

                $product = $product->toArray();
                $product['item_name'] = $product['title'];
                $qty = $request->get('qty') * $product['qty_include'];
                if ($product['price'] === 0) {
                    $result['product'] = $product;
                    $result['user_id'] = $user_id;
                    $result['online'] = $online;
                    $result['price'] = $product['price'];
                    $result['handphone'] = $handphone;
                    $result['order_id'] = $order_id;
                    $result['type'] = $type;
                    $result['qty'] = $qty;
                    // $saveDb = self::saveToDB($result);
                    // if (!$saveDb) {
                    //     return $this->sendError(error: 'Cant save to DB', code: 500);
                    // }

                    DB::commit();
                    unset( $result['product']);
                    $result['payment_progress'] = 'success';
                    $result['va_number'] = '';
                    return $this->sendResponse(result: $result, message: 'success');
                }

                $qty = $request->get('qty') * $product['qty_include'];
                if ($qty > $product['limit']) {
                    return $this->sendError(error: 'Jumlah maksimum', code: 400);
                }

                $checkQuotaByUser = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->where('transaction_detail.detail_event_id', $id)
                    ->whereIn('transaction.payment_progress', ['success', 'booking'])
                    ->where('transaction.user_id', $user['id'])->sum('transaction_detail.qty');
                if ($checkQuotaByUser >= $product['limit']) {
                    return $this->sendError(error: 'Jumlah maksimum', code: 400);
                }

                $checkQuota = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->whereIn('transaction.payment_progress', ['success', 'booking'])
                    ->where('transaction_detail.detail_event_id', $id)
                    ->sum('transaction_detail.qty');
                if ($checkQuota >= $product['quota']) {
                    return $this->sendError(error: 'Kuota habis', code: 400);
                }

            } elseif ($type == 'online-course') {
                $select = [
                    'online_course.id',
                    'online_course.title',
                    'online_course.price',
                    'online_course.type',
                    'online_course.voucher',
                    'online_course.master_app_id',
                    'online_course.master_category_id',
                    'master_category.category_name'
                ];
                $product = OnlineCourse::select($select)
                    ->join('master_category', 'master_category.id', '=', 'online_course.master_category_id')
                    ->where('id', $id)->where('status', 'publish')->first();
                if (!$product) {
                    return $this->sendError(error: 'Kursus Online tidak ditemukan');
                }

                $product = $product->toArray();
                $product['item_name'] = $product['title'];
                if ($product['price'] === 0) {
                    $result['product'] = $product;
                    $result['user_id'] = $user_id;
                    $result['online'] = $online;
                    $result['price'] = $product['price'];
                    $result['handphone'] = $handphone;
                    $result['order_id'] = $order_id;
                    $result['type'] = $type;
                    $result['qty'] = $qty;
                    $result['va_number'] = '';
                    // $saveDb = self::saveToDB($result);
                    // if (!$saveDb) {
                    //     return $this->sendError(error: 'Cant save to DB', code: 500);
                    // }

                    $result['payment_progress'] = 'success';
                    unset( $result['product']);
                    DB::commit();
                    return $this->sendResponse(result: $result, message: 'success');
                }

                $checkTransactionBook = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->where('transaction_detail.online_course_id', $id)
                    ->whereIn('transaction.payment_progress', ['booking'])
                    ->where('transaction.user_id', $user['id'])->count('transaction_detail.id');
                if($checkTransactionBook>0){
                    return $this->sendError(error: 'Selesaikan pembayaran terlebih dahulu', code: 400);
                }

                $checkTransactionPurchased = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->where('transaction_detail.online_course_id', $id)
                    ->whereIn('transaction.payment_progress', ['success'])
                    ->where('transaction.user_id', $user['id'])->count('transaction_detail.id');
                if($checkTransactionPurchased>0){
                    return $this->sendError(error: 'Produk dibeli', code: 400);
                }

            } else if($type == 'membership') {
                $select = [
                    'membership_duration.id',
                    'membership_duration.membership_plan_id',
                    'membership_duration.name',
                    'membership_duration.type',
                    'membership_duration.duration',
                    'membership_duration.price',
                    'membership_plan.plan_name',
                ];
                $product = MembershipDuration::select($select)
                    ->join('membership_plan', 'membership_plan.id', 'membership_duration.membership_plan_id')
                    ->where('id', $id)->first();
                if (!$product) {
                    return $this->sendError(error: 'Durasi Keanggotaan tidak ditemukan');
                }

                $product = $product->toArray();
                $product['item_name'] = $product['plan_name'];
                $product['category_name'] = 'Membership';

                if ($product['price'] === 0) {
                    $result['product'] = $product;
                    $result['user_id'] = $user_id;
                    $result['online'] = $online;
                    $result['price'] = $product['price'];
                    $result['handphone'] = $handphone;
                    $result['order_id'] = $order_id;
                    $result['type'] = $type;
                    $result['qty'] = $qty;
                    $result['va_number'] = '';
                    // $saveDb = self::saveToDB($result);
                    // if (!$saveDb) {
                    //     return $this->sendError(error: 'Cant save to DB', code: 500);
                    // }

                    $result['payment_progress'] = 'success';
                    unset( $result['product']);
                    DB::commit();
                    return $this->sendResponse(result: $result, message: 'success');
                }

                $checkTransactionBook = TransactionDetail::join('transaction', 'transaction.id', '=', 'transaction_detail.transaction_id')
                    ->where('transaction_detail.membership_duration_id', $id)
                    ->whereIn('transaction.payment_progress', ['booking'])
                    ->where('transaction.user_id', $user['id'])->count('transaction_detail.id');
                if($checkTransactionBook>0){
                    return $this->sendError(error: 'Selesaikan pembayaran terlebih dahulu', code: 400);
                }
            }


            if ($voucher) {
                if (empty($product['voucher'])) {
                    return $this->sendError(error: 'Voucher tidak tersedia untuk produk ini', code: 400);
                }
                $checkVoucher = self::voucherAvailable($voucher, $today, $app);

                if (!$checkVoucher) {
                    return $this->sendError(error: 'Voucher tidak tersedia');
                }

                $checkVoucherByUser = Transaction::select('id')->where('voucher_id', $checkVoucher['id'])->where('user_id', $user['id'])->count(['id']);
                if ($checkVoucherByUser) {
                    return $this->sendError(error: 'Voucher sudah digunakan', code: 400);
                }

                $checkVoucherTransaction = Transaction::select('id')->where('voucher_id', $checkVoucher['id'])->count(['id']);
                if ($checkVoucherTransaction > $checkVoucher['qty']) {
                    return $this->sendError(error: 'Voucher habis', code: 400);
                }

                $checkVoucher['voucher_number'] = $voucher;
            }

            $discount = 0;
            $originalPrice = $product['price'];
            if ($product && $voucher) {
                $discountMax = $checkVoucher['max_discount'];
                $discountType = $checkVoucher['type'];
                $discount = $checkVoucher['discount'];
                if ($discountType == 'nominal') {
                    $newDiscount = $discount > $discountMax ? $discountMax : $discount;
                    $amount = $product['price'] - $newDiscount;
                    $discount = $newDiscount;
                } elseif ($discountType == 'percent') {
                    $caculateDiscount = ($discount / 100) * $product['price'];
                    $newDiscount = $caculateDiscount > $discountMax ? $discountMax : $caculateDiscount;
                    $amount = $product['price'] - $newDiscount;
                    $discount = $newDiscount;
                }
            } else {
                $amount = $product['price'];
            }

            $amount = $amount . '.00';

            $email = $request->get('email') ? $request->get('email') : $user['email'];
            $ccy = ENV("ESPAY_CURRENCY", 'IDR');
            $key = ENV("ESPAY_KEY");
            $handphone = !empty($user['no_hp']) ? $user['no_hp'] : env('ESPAY_PHONE');
            $comm_code = ENV("ESPAY_COMM_ID");

            $exp = 60 * 24;
            $signature_text = strtoupper("##$key##$rq_uuid##$rq_datetime##$order_id##$amount##$ccy##$comm_code##SENDINVOICE##");
            $signature = hash("sha256", $signature_text);
            $payload = [
                "rq_uuid" => $rq_uuid,
                "rq_datetime" => $rq_datetime,
                "order_id" => $order_id,
                "amount" => $amount,
                "ccy" => $ccy,
                "comm_code" => $comm_code,
                "remark1" => $handphone,
                "remark2" => $user['first_name'],
                "remark3" => $email,
                "update" => "N",
                "bank_code" => $bank_code,
                "va_expired" => $exp,
                "description" => 'Pembelian ' . $type . ' ' . $today,
                "signature" => $signature,
            ];

            // $setInquiry = Helper::setRedis('inquiry_' . $order_id, json_encode($payload, JSON_UNESCAPED_SLASHES), $this->ttlFiveDay);
            if ($setInquiry) {
                $response = Http::asForm()->post($this->urlPayment . '/rest/merchantpg/sendinvoice', $payload);
                $result = json_decode($response->body(), true);
                if(!empty($result['error_code']) && $result['error_code'] != '0000') {
                    return $this->sendError('Buat Transaksi Pembayaran Gagal', $result, code: 500);
                }

                $result['online'] = $online;
                $result['product'] = $product;
                $result['order_id'] = $order_id;
                $result['amount'] = $amount;
                $result['handphone'] = $handphone;
                $result['original_price'] = $originalPrice;
                $result['voucher_number'] = $voucher ?? null;
                $result['discount'] = $discount;
                $result['payment_method'] = $bank['productName'];
                $result['type'] = $type;
                $result['user_id'] = $user['id'];
                $result['qty'] = $qty;

                if ($voucher) {
                    $result['voucher'] = $checkVoucher;
                }
                // $saveDb = self::saveToDB($result);
                // if (!$saveDb) {
                //     return $this->sendError(error: 'Cant save to DB', code: 500);
                // }

                // $updateVaNumber = Transaction::find($saveDb);
                // $updateVaNumber->va_number = $result['va_number'];
                // $updateVaNumber->save();
                DB::commit();
                unset($result['product']);
                unset($result['type']);
                if (isset($result['voucher'])) {
                    unset($result['voucher']);
                }

                return $this->sendResponse(result: $result, message: 'success');
            } else {
                return $this->sendError(error: 'redis gagal', code: 500);
            }
        } catch (\Exception $e) {
            DB::rollback();
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    private function generateOrderId($prefix)
    {
        $rand1 = random_int(100, 999);
        $rand2 = random_int(100, 999);
        $rand3 = random_int(100, 999);
        $date = date('Y/m/d');

        $microtime = str_replace(':', '', date('h:i:s')) . $rand1 . $rand2 . $rand3;
        return $prefix . '/' . $date . '/' . strtoupper(Str::random(6)) . $microtime;
    }

    public static function calculateDiscount($dataVoucher, $price)
    {
        // dd($dataVoucher, $price);
        $discountMax = $dataVoucher['max_discount'];
        $discountType = $dataVoucher['type'];
        $discount = $dataVoucher['discount'];
        $amount = 0;
        if ($discountType == 'amount') {
            $newDiscount = $discount > $discountMax ? $discountMax : $discount;
            $amount = $price - $newDiscount;
            $discount = $newDiscount;

        } elseif ($discountType == 'percent') {
            $caculateDiscount = floor( ($discount / 100) * $price );
            $newDiscount = $caculateDiscount > $discountMax ? $discountMax : $caculateDiscount;
            $amount = $price - $newDiscount;
            $discount = $newDiscount;
        }

        return [
            'amount' => $amount,
            'discount' => (int) $discount,
        ];
    }

    public function createV2(Request $request)
    {
        $user = $request->get('session_user');
        if (empty($request->bank_code)) {
            return $this->sendError(error: 'Kode Bank diperlukan', code: 400);
        }

        $membershipDurationId = $request->get('membership_duration_id');
        if(!empty($membershipDurationId)) {
            $cartItems = [
                [
                    'type' => 'membership',
                    'qty' => 1,
                    'content_id' => $membershipDurationId
                ]
            ];

        } else {
            $cartItems = CartV2::where('user_id', $user['id'])->get()->toArray();
            if(count($cartItems) == 0) {
                return $this->sendError('Keranjang Anda kosong.', code: 400);
            }
        }

        DB::beginTransaction();
        $bank_code = $request->get('bank_code');
        $listBank = self::listBank()->getOriginalContent()['data'];
        $arrBankCode = array_column($listBank, 'bankCode');
        $searchBank = array_search($bank_code, $arrBankCode);

        if ($searchBank === false) {
            return $this->sendError(error: 'Metode Pembayaran tidak diizinkan');
        }

        $bank = $listBank[ $searchBank ];
        $voucher = $request->get('voucher');
        $isVoucher = !empty($voucher);
        $handphone = !empty($user['no_hp']) ? $user['no_hp'] : env('ESPAY_PHONE');
        $email = $user['email'];

        try {
            $rq_uuid = strtoupper(Str::random(6) . "-" . Str::random(13));
            $rq_datetime = Carbon::now()->format("Y-m-d H:m:s");
            $today = date('Y-m-d');
            $transactionDetailData = [];
            $totalPrice = 0;
            $order_id = self::generateOrderId('PTRX');

            foreach($cartItems as $cartValue) {
                $online = true;

                if($cartValue['type'] == 'event') {
                    $select = [
                        'detail_event.id',
                        'event.title',
                        'detail_event.price',
                        'detail_event.voucher',
                        'detail_event.qty_include',
                        'detail_event.limit',
                        'detail_event.quota',
                        'detail_event.ticket_pass',
                        'detail_event.ticket_pass_id',
                        'event.master_app_id',
                        'event.master_category_id',
                        'event.type',
                        'master_category.category_name',
                        'detail_event.date',
                    ];
                    $product = DetailEvent::select($select)
                        ->join('event', 'detail_event.event_id', '=', 'event.id')
                        ->join('master_category', 'master_category.id', '=', 'event.master_category_id')
                        ->where('event.status', 'publish')
                        ->where('detail_event.status', 'publish')
                        ->where('detail_event.id', $cartValue['content_id'])->first();
                    // dd($product, "Event");
                    if ( !$product || (!$product->ticket_pass && strtotime(date('Y-m-d')) > strtotime($product->date)) ) {
                        return $this->sendError(
                            error: 'Acara tidak ditemukan',
                            errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                        );
                    }

                    if($product['type'] == 'offline'){
                        $online = false;
                    }

                    $product = $product->toArray();
                    $qty = $cartValue['qty'] * $product['qty_include'];

                    if ($qty > $product['limit']) {
                        return $this->sendError(
                            error: 'Jumlah maksimum',
                            code: 400,
                            errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                        );
                    }

                    $checkQty = TransactionController::validateEventQty($product, $qty);
                    if(!$checkQty['s']) {
                        return $this->sendError(error: $checkQty['m'], code: 400);
                    }

                    $price = $product['price'] * $cartValue['qty'];
                    $transactionDetailData[] = [
                        'product' => $product,
                        'master_category_id' => $product['master_category_id'],
                        'master_app_id' => $product['master_app_id'],
                        'transaction_type' => $cartValue['type'],
                        'online' => $online,
                        'qty' => $cartValue['qty'],
                        'ticket_pass' => $product['ticket_pass'],
                    ];

                } else if($cartValue['type'] == 'online-course') {
                    $select = [
                        'online_course.id',
                        'online_course.title',
                        'online_course.price',
                        'online_course.promo_price',
                        'online_course.type',
                        'online_course.voucher',
                    ];
                    $product = OnlineCourse::select($select)
                        ->where('id', $cartValue['content_id'])->where('status', 'publish')->first();

                    if (!$product) {
                        return $this->sendError(
                            error: 'Kursus Online tidak ditemukan',
                            errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                        );
                    }

                    $product = $product->toArray();
                    $price = $product['promo_price'] > 0 ? $product['promo_price'] : $product['price'];
                    $product['price'] = $price;
                    unset($product['promo_price']);

                    $transactionDetailData[] = [
                        'product' => $product,
                        'transaction_type' => $cartValue['type'],
                        'online' => $online,
                    ];

                } else if($cartValue['type'] == 'membership') {
                    if(!empty($user['membership_plan_id'])) {
                        $membershipPlanUsed = MembershipPlan::find($user['membership_plan_id']);
                        if(
                            is_object($membershipPlanUsed) &&
                            (!$membershipPlanUsed->is_default || strtotime($membershipPlanUsed->membership_exp) > strtotime('now'))
                        ) {
                            return $this->sendError(
                                error: 'Anda masih memiliki langganan keanggotaan',
                                code: 400,
                                errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                            );
                        }
                    }

                    $select = [
                        'membership_duration.id',
                        'membership_duration.membership_plan_id',
                        'membership_duration.name',
                        'membership_duration.type',
                        'membership_duration.duration',
                        'membership_duration.price',
                        'membership_plan.plan_name',
                    ];
                    $product = MembershipDuration::select($select)
                        ->join('membership_plan', 'membership_plan.id', 'membership_duration.membership_plan_id')
                        ->where('membership_duration.id', $cartValue['content_id'])->first();
                    if (!$product) {
                        return $this->sendError(
                            error: 'Durasi Keanggotaan tidak ditemukan',
                            errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                        );
                    }

                    $product = $product->toArray();
                    $price = $product['price'];
                    $product['item_name'] = $product['plan_name'];
                    $product['category_name'] = 'Membership';

                    $transactionDetailData[] = [
                        'product' => $product,
                        'transaction_type' => $cartValue['type'],
                        'online' => $online,
                    ];
                }

                $totalPrice += $price;
            }

            $totalFee = 0;
            $transactionFee = [];
            // dd($totalPrice);
            if($totalPrice > 0) {
                $transactionFee = MasterTransactionFee::select(['id', 'title', 'fee', 'fee_type'])
                    ->where('status', 'publish')->orderBy('id', 'ASC')->get()->toArray();

                foreach($transactionFee as $key => $value) {
                    if($value['fee_type'] == 'percent') {
                        $value['fee'] = floor(($totalPrice / 100) * $value->fee);
                    }

                    $transactionFee[$key]['fee'] = $value['fee'];
                    $totalFee += $value['fee'];
                }
            }

            if ($isVoucher) {
                $checkVoucher = self::voucherAvailable($voucher, $today);
                if (!$checkVoucher) {
                    return $this->sendError(error: 'Voucher tidak tersedia');
                }

                $checkVoucherByUser = Transaction::select('id')->where('voucher_number', $voucher)
                    ->where('user_id', $user['id'])->count(['id']);
                if ($checkVoucherByUser >= $checkVoucher['limit']) {
                    return $this->sendError(error: 'Voucher sudah digunakan', code: 400);
                }

                $checkVoucherTransaction = Transaction::select('id')->where('voucher_number', $voucher)->count(['id']);
                if ($checkVoucherTransaction > $checkVoucher['qty']) {
                    return $this->sendError(error: 'Voucher habis', code: 400);
                }

                $checkVoucher['voucher_number'] = $voucher;
            }

            if($isVoucher && $price > 0) {
                $calculateDiscount = self::calculateDiscount($checkVoucher, $totalPrice);
                $amount = $calculateDiscount['amount'];
                $discount = $calculateDiscount['discount'];
            } else {
                $amount = $totalPrice;
            }

            $amount = $amount < 0 ? 0 : $amount;
            $amount += $totalFee;
            // dd($amount);
            if($amount == 0) {
                $transaction = [
                    'user_id' => $user['id'],
                    'order_id' => $order_id,
                    'price' => $totalPrice,
                    'discount_type' => $isVoucher ? $checkVoucher['type'] : null,
                    'discount' => $isVoucher ? $discount : null,
                    'voucher_number' => $isVoucher ? $voucher : null,
                    'handphone' => $handphone,
                    'fee_pg' => 0,
                    'total_amount' => $amount,
                ];

                $saveDb = self::saveToDB($transaction, $transactionDetailData, $transactionFee);
                if (!$saveDb) {
                    return $this->sendError(error: 'Cant save to DB', code: 500);
                }
                $transactionDetail = TransactionDetail::select(['id', 'transaction_type', 'membership_duration_id', 'detail_event_id'])
                ->where('transaction_id', $saveDb)->get();

                $onlineTicket = [];
                foreach($transactionDetail as $detailValue) {
                    if($detailValue->transaction_type == 'event') {
                        $selectEvent = [
                            'event.title',
                            'master_category.category_name',
                            'event.cover_image',
                            'event.address',
                            'event.type',
                            'users.email AS customer_email',
                            DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS customer_name"),
                            'detail_event.url_meeting',
                            'detail_event.date',
                            'detail_event.start_time',
                            'detail_event.end_time',
                            'transaction_detail.price',
                            'transaction_detail.detail_event_id',
                            'transaction_detail.qty',
                            'transaction.order_id',
                            'detail_event.ticket_pass',
                            'detail_event.ticket_pass_id',
                            'transaction.payment_progress',
                        ];
                        $event = TransactionDetail::select($selectEvent)
                            ->join('detail_event', 'detail_event.id', 'transaction_detail.detail_event_id')
                            ->join('event', 'event.id', 'detail_event.event_id')
                            ->join('master_category', 'master_category.id', 'event.master_category_id')
                            ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
                            ->join('users', 'users.id', 'transaction.user_id')
                            ->where('transaction_detail.id', $detailValue->id)
                            ->first()->toArray();
                        
                        if($event['ticket_pass']) {
                            for($i=1; $i <= $event['qty']; $i++) {
                                $ticketPassId = json_decode($event['ticket_pass_id'], true);
                                $eventPass = DetailEvent::select(['id', 'title', 'url_meeting', 'date', 'start_time', 'end_time'])
                                    ->whereIn('id', $ticketPassId)->get()->toArray();
    
                                $event['ticket_pass_item'] = $eventPass;
                                $onlineTicket[] = $event;
    
                                if($event['type'] == 'offline') {
                                    $event['ticket_pass'] = false;
                                    $event['ticket_pass_id'] = null;
    
                                    foreach($eventPass as $valueTicketPass) {
                                        $qrCode = self::generateQr();
                                        TransactionTicketQr::create([
                                            'transaction_detail_id' => $detailValue->id,
                                            'detail_event_id' => $valueTicketPass['id'],
                                            'qr_code' => $qrCode,
                                            'qr_used' => false,
                                        ]);
    
                                        $event['url_meeting'] = $valueTicketPass['url_meeting'];
                                        $event['date'] = $valueTicketPass['date'];
                                        $event['start_time'] = $valueTicketPass['start_time'];
                                        $event['end_time'] = $valueTicketPass['end_time'];
    
                                        $event['qr_code'] = $qrCode;
                                        $onlineTicket[] = $event;
                                    }
                                }
                            }
                        } else {
                            if($event['type'] == 'offline') {
                                for($i=1; $i <= $event['qty']; $i++) {
                                    $qrCode = self::generateQr();
                                    TransactionTicketQr::create([
                                        'transaction_detail_id' => $detailValue->id,
                                        'detail_event_id' => $detailValue->detail_event_id,
                                        'qr_code' => $qrCode,
                                        'qr_used' => false,
                                    ]);
    
                                    $event['qr_code'] = $qrCode;
                                    $onlineTicket[] = $event;
                                }
    
                            } else {
                                $onlineTicket[] = $event;
                            }
                        }
                    } else if($detailValue->transaction_type == 'online-course') {
                        $selectCourse = [
                            'online_course.title',
                            'online_course.image AS cover_image',
                            'users.email AS customer_email',
                            DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS customer_name"),
                            'transaction_detail.price',
                            'transaction_detail.online_course_id',
                            'transaction.order_id',
                            'instructor.name AS instructor_name',
                            'online_course.video_length',
                        ];
    
                        $onlineCourse = TransactionDetail::select($selectCourse)
                            ->join('online_course', 'online_course.id', 'transaction_detail.online_course_id')
                            ->join('mapping_instructor', 'mapping_instructor.online_course_id', 'online_course.id')
                            ->join('instructor', 'instructor.id', 'instructor_id')
                            ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
                            ->join('users', 'users.id', 'transaction.user_id')
                            ->where('transaction_detail.id', $detailValue->id)
                            ->first()->toArray();
    
                        unset($onlineCourse['promo_price']);
                        $onlineCourse['url_course'] = env('FRONT_APP_URL');
                        $onlineCourse['duration'] = Helper::formatDuration( Helper::getDuration($onlineCourse['video_length']) );
                        $onlineTicket[] = $onlineCourse;
                    }
                }

                if(count($onlineTicket) > 0) {
                    $notifMail = new NotifTransaksiMail($onlineTicket);
                    Mail::to($user['email'])->send($notifMail);
                    // dd('$amountttenmaikl');
                }
                // dd('$amountttenmaikl');

                Notification::create([
                    'user_id' => $user['id'],
                    'transaction_id' => $saveDb,
                    'title' => "Pembelian Berhasil",
                    'content' => "Pembelian Anda dengan No Order {$order_id}\" {berhasil di konfirmasi.}",
                    'is_read' => false,
                ]);

                CartV2::where('user_id', $user['id'])->delete();
                DB::commit();

                $result = [];
                $result['order_id'] = $order_id;
                $result['handphone'] = $handphone;
                $result['va_number'] = '';
                $result['payment_progress'] = 'success';
                $result['total_amount'] = $amount;
                return $this->sendResponse(result: $result, message: 'success');
            }

            $email = $request->get('email') ? $request->get('email') : $user['email'];
            $ccy = ENV("ESPAY_CURRENCY", 'IDR');
            $key = ENV("ESPAY_KEY");
            $handphone = !empty($user['no_hp']) ? $user['no_hp'] : env('ESPAY_PHONE');
            $comm_code = ENV("ESPAY_COMM_ID");

            $amount = $amount . '.00';
            $exp = 60 * 24;
            $signature_text = strtoupper("##$key##$rq_uuid##$rq_datetime##$order_id##$amount##$ccy##$comm_code##SENDINVOICE##");
            $signature = hash("sha256", $signature_text);
            $payload = [
                "rq_uuid" => $rq_uuid,
                "rq_datetime" => $rq_datetime,
                "order_id" => $order_id,
                "amount" => $amount,
                "ccy" => $ccy,
                "comm_code" => $comm_code,
                "remark1" => $handphone,
                "remark2" => $user['first_name'],
                "remark3" => $email,
                "update" => "N",
                "bank_code" => $bank_code,
                "va_expired" => $exp,
                "description" => 'Pembelian ' . $today,
                "signature" => $signature,
            ];

            // $setInquiry = Helper::setRedis('inquiry_' . $order_id, json_encode($payload, JSON_UNESCAPED_SLASHES), $this->ttlFiveDay);
            if ($setInquiry) {
                $response = Http::asForm()->timeout(60)->post($this->urlPayment . '/rest/merchantpg/sendinvoice', $payload);
                $result = json_decode($response->body(), true);
                if(!empty($result['error_code']) && $result['error_code'] != '0000') {
                    return $this->sendError('Buat Transaksi Pembayaran Gagal', $result, code: 500);
                }

                $transaction = [
                    'user_id' => $user['id'],
                    'order_id' => $order_id,
                    'price' => $totalPrice,
                    'payment_method' => $bank['productName'],
                    'discount_type' => $isVoucher ? $checkVoucher['type'] : null,
                    'discount' => $isVoucher ? $discount : null,
                    'voucher_number' => $isVoucher ? $voucher : null,
                    'va_number' => $result['va_number'],
                    'handphone' => $handphone,
                    'fee_pg' => $result['fee'],
                    'total_amount' => $amount + $result['fee'],
                ];

                $saveDb = self::saveToDB($transaction, $transactionDetailData, $transactionFee);
                if (!$saveDb) {
                    return $this->sendError(error: 'Cant save to DB', code: 500);
                }

                CartV2::where('user_id', $user['id'])->delete();
                DB::commit();

                $result['order_id'] = $order_id;
                return $this->sendResponse(result: $result, message: 'success');

            } else {
                return $this->sendError(error: 'redis gagal', code: 500);
            }

        } catch (\Exception $e) {
            DB::rollback();
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    private function saveToDB($result, $transactionDetailData, $transactionFee)
    {
        try {
            $transaction = new Transaction();
            $transaction->payment_progress = $result['price'] === 0 ? 'success' : 'booking';
            $transaction->price = $result['price'];
            $transaction->user_id = $result['user_id'];
            $transaction->fee_pg = $result['fee_pg'];
            $transaction->total_amount = $result['total_amount'];
            $transaction->handphone = $result['handphone'];
            $transaction->discount = $result['discount'] ?? 0;
            $transaction->payment_method = $result['payment_method'] ?? null;
            $transaction->order_id = $result['order_id'];
            $transaction->va_number = $result['va_number'] ?? null;
            $transaction->discount_type = $result['discount_type'] ?? null;
            $transaction->voucher_number = $result['voucher_number'] ?? null;

            $transaction->save();
            $transaction_id = $transaction->id;

            $notifMessage = $transaction->payment_progress == 'success' ? 'berhasil di konfirmasi' : 'telah dibuat, harap segera menyelesaikan pembayaran anda';
            Notification::create([
                'user_id' => $result['user_id'],
                'transaction_id' => $transaction_id,
                'title' => 'Pembelian Dibuat',
                'content' => "Pembelian Anda dengan No Order {$result['order_id']}\" {$notifMessage}.",
                'is_read' => false,
            ]);


            self::saveTransactionDetail($transaction_id,$transactionDetailData);
            self::saveTransactionFee($transaction_id,$transactionFee);
            return $transaction_id;
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
            return false;
        }
    }

    private function saveTransactionDetail($transaction_id,$transactionDetailData){
        foreach($transactionDetailData as $value) {
            if ($value['transaction_type'] == 'event') {
                $value['detail_event_id'] = $value['product']['id'];
            } elseif ($value['transaction_type'] == 'online-course') {
                $value['online_course_id'] = $value['product']['id'];
            } elseif ($value['transaction_type'] == 'membership') {
                $value['membership_duration_id'] = $value['product']['id'];
            }

            if(isset($value['ticket_pass']) && $value['ticket_pass']){
                if($value['product']['ticket_pass_id']){
                    $getTicketPass = json_decode($value['product']['ticket_pass_id'],true);
                    foreach($getTicketPass as $ticket){
                        TransactionTicketPass::create([
                            'transaction_id' => $transaction_id,
                            'ticket_pass_id' => $value['product']['id'],
                            'detail_event_id' => $ticket,
                        ]);
                    }
                }
            }

            TransactionDetail::create([
                'transaction_id' => $transaction_id,
                'detail_event_id' => $value['detail_event_id'] ?? null,
                'membership_duration_id' => $value['membership_duration_id'] ?? null,
                'online_course_id' => $value['online_course_id'] ?? null,
                'qty' => $value['qty'] ?? null,
                'master_category_id' => $value['master_category_id'] ?? null,
                'transaction_type' => $value['transaction_type'],
                'master_app_id' => $value['master_app_id'] ?? null,
                'online' => $value['online'],
                'ticket_pass' => $value['ticket_pass'] ?? false,
                'price' => $value['product']['price'] ?? false,
            ]);
        }
    }

    private function saveTransactionFee($transaction_id, $transactionFee) {
        foreach($transactionFee as $value) {
            TransactionFee::create([
                'transaction_id' => $transaction_id,
                'master_transaction_fee_id' => $value['id'],
                'fee' => $value['fee'],
                'fee_type' => $value['fee_type'],
            ]);
        }
    }


    private function quotaVoucherUsed($voucher_id, $limit)
    {
        try {
            $select = [
                'id',
            ];
            $checkTransaction = Transaction::select('id')
                ->where('voucher_id', $voucher_id)->count();
            if ($checkTransaction > $limit) {
                return false;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function voucherAvailable($voucher, $today)
    {
        
        try {
            $select = [
                'voucher.id',
                'voucher.discount',
                'voucher.qty',
                'voucher.limit',
                'voucher.max_discount',
                'voucher.type',
            ];

            $findVoucher = Voucher::select($select)
                ->whereDate('voucher.start_date', '<=', $today)
                ->where('voucher.voucher_number', $voucher)
                ->where('voucher.status', 'active')->first();
                // dd($findVoucher);
            if (!$findVoucher) {
                return false;
            }
            // dd($findVoucher->toArray());

            return $findVoucher->toArray();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
            return false;
        }
    }

    private function checkAvailableDetailEvent($id): bool
    {
        try {
        } catch (\Exception $e) {
            return false;
        }


    }


    public function inquiry(Request $request)
    {
        $order_id = $request->get('order_id');
        // $getRedis = Helper::getRedis('inquiry_' . $order_id);
        // $getData = json_decode($getRedis, true);
        $ip = $request->ip();
        // Helper::setRedis('inquiry_ip_' . $ip . '_' . $order_id, json_encode($request->all()), $this->ttlFiveDay);
        $date = date('d/m/y H:i:s');
        $amount = $getData['amount'];
        $text = "0;Success;$order_id;$amount;IDR;Payment For Me;$date";
        $response = Response::make($text, 200);
        $response->header('Content-Type', 'text/plain');

        return $response;
    }

    public function statusPayment(Request $request)
    {
        try {
            $order_id = $request->get('order_id');
            $transaction = Transaction::where('order_id', $order_id)->first();
            if(!$transaction) {
                return $this->sendError('Transaksi Tidak Ditemukan');
            }

            $rq_uuid = strtoupper(Str::random(6) . "-" . Str::random(13));
            $rq_datetime = Carbon::now()->format("Y-m-d H:m:s");
            $comm_code = ENV("ESPAY_COMM_ID");
            $key = ENV("ESPAY_KEY");
            $signature_text = strtoupper("##$key##$rq_datetime##{$order_id}##CHECKSTATUS##");
            $signature = hash("sha256", $signature_text);
            $payload = [
                "uuid" => $rq_uuid,
                "rq_datetime" => $rq_datetime,
                "order_id" => $order_id,
                "comm_code" => $comm_code,
                "signature" => $signature,
            ];

            $response = Http::asForm()->post($this->urlPayment . '/rest/merchant/status', $payload);
            $result = json_decode($response->body(), true);
            if($result['error_code'] != '0000') {
                return $this->sendError('Check Status Payment Failed', ['error_code' => $result['error_code'], 'error_message' => $result['error_message']], 500);
            }

            $arrPanduanPg = file_get_contents( env('ESPAY_PANDUAN') );
            $arrPanduanPg = json_decode($arrPanduanPg, true);

            $result['bank_image'] = $this->bank_image[ $result['debit_from_bank'] ] ?? '';
            $result['intruction'] = [];

            foreach($arrPanduanPg as $val) {
                if( preg_match("/{$val['bankName']}/i", $result['bank_name']) == 1 ) {
                    $result['intruction'] = $val['intruction'];
                    break;
                }
            }

            $result['fee_pg'] = $transaction->fee_pg;
            $result['total_fee'] = TransactionFee::select('fee')->where('transaction_id', $transaction->id)->sum('fee');
            $result['total_amount'] = $transaction->total_amount;
            $result['va_number'] = $transaction->va_number;
            $result['payment_progress'] = $this->payment_progress[ $result['tx_status'] ] ?? null;
            // dd($result);
            return $this->sendResponse(result: $result, message: 'success');

        } catch (\Exception $e) {
            DB::rollback();
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }



    public function getStatusPayment(Request $request)
    {
        DB::beginTransaction();
        $reconcile_id = strtoupper(Str::random(6) . "-" . Str::random(13));
        $order_id = $request->get('order_id');
        $password = $request->get('password');
        $date = date('Y-m-d H:i:s');

        // validasi PG password
        // if($password != env('ESPAY_PASSWORD')) {
        //     $text = "1,password not valid,$reconcile_id,$order_id;$date";
        //     $response = Response::make($text, 200);
        //     $response->header('Content-Type', 'text/plain');
        //     return $response;
        // }

        // $rq_datetime = $request->get('rq_datetime');
        // $signKey = ENV("ESPAY_KEY");
        // $validSignature = strtoupper("##$signKey##$rq_datetime##$order_id##PAYMENTREPORT##");
        // $validSignature = hash('sha256', $validSignature);

        // $signature = $request->get('signature');
        // if($signature != $validSignature) {
        //     $text = "1,signature not valid,$reconcile_id,$order_id;$date";
        //     $response = Response::make($text, 200);
        //     $response->header('Content-Type', 'text/plain');
        //     return $response;
        // }

        // try {
            $ip = $request->ip();
            $status = $request->get('tx_status');
            $payment_progress = $this->payment_progress[$status];

            $transaction = Transaction::where('order_id', $order_id)->first();
            $transaction_id = $transaction->id;
            $transaction_status = $transaction->payment_progress;

            if(!$transaction){
                $text = "1,order_id not found,$reconcile_id,$order_id;$date";
                $response = Response::make($text, 200);
                $response->header('Content-Type', 'text/plain');
                return $response;
            }

            $updateTransaction = $transaction->update(['payment_progress' => $payment_progress]);
            if(!$updateTransaction){
                $message = "Tidak dapat memperbarui db";
                DB::rollback();
                $text = "1,$message,$reconcile_id,$order_id,$date";
                $response = Response::make($text, 200);
                $response->header('Content-Type', 'text/plain');
            }

            $transaction = $transaction->toArray();
            if($transaction_status != 'success' && $payment_progress == 'success') {
                $transactionDetail = TransactionDetail::select(['id', 'transaction_type', 'membership_duration_id'])
                    ->where('transaction_id', $transaction_id)->get();

                $onlineTicket = [];
                foreach($transactionDetail as $detailValue) {
                    if($detailValue->transaction_type == 'event') {
                        $selectEvent = [
                            'event.title',
                            'master_category.category_name',
                            'event.cover_image',
                            'event.address',
                            'event.type',
                            'users.email AS customer_email',
                            DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS customer_name"),
                            'detail_event.url_meeting',
                            'detail_event.date',
                            'detail_event.start_time',
                            'detail_event.end_time',
                            'transaction_detail.price',
                            'transaction_detail.detail_event_id',
                            'transaction_detail.qty',
                            'transaction.order_id',
                            'detail_event.ticket_pass',
                            'detail_event.ticket_pass_id',
                            'transaction.payment_progress',
                        ];

                        $event = TransactionDetail::select($selectEvent)
                            ->join('detail_event', 'detail_event.id', 'transaction_detail.detail_event_id')
                            ->join('event', 'event.id', 'detail_event.event_id')
                            ->join('master_category', 'master_category.id', 'event.master_category_id')
                            ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
                            ->join('users', 'users.id', 'transaction.user_id')
                            ->where('transaction_detail.id', $detailValue->id)
                            ->first()->toArray();

                        if($event['ticket_pass']) {
                            for($i=1; $i <= $event['qty']; $i++) {
                                $ticketPassId = json_decode($event['ticket_pass_id'], true);
                                $eventPass = DetailEvent::select(['id', 'title', 'url_meeting', 'date', 'start_time', 'end_time'])
                                    ->whereIn('id', $ticketPassId)->get()->toArray();

                                $event['ticket_pass_item'] = $eventPass;
                                $onlineTicket[] = $event;

                                if($event['type'] == 'offline') {
                                    $event['ticket_pass'] = false;
                                    $event['ticket_pass_id'] = null;

                                    foreach($eventPass as $valueTicketPass) {
                                        $qrCode = self::generateQr();
                                        TransactionTicketQr::create([
                                            'transaction_detail_id' => $detailValue->id,
                                            'detail_event_id' => $valueTicketPass['id'],
                                            'qr_code' => $qrCode,
                                            'qr_used' => false,
                                        ]);

                                        $event['url_meeting'] = $valueTicketPass['url_meeting'];
                                        $event['date'] = $valueTicketPass['date'];
                                        $event['start_time'] = $valueTicketPass['start_time'];
                                        $event['end_time'] = $valueTicketPass['end_time'];

                                        $event['qr_code'] = $qrCode;
                                        $onlineTicket[] = $event;
                                    }
                                }
                            }

                        } else {
                            if($event['type'] == 'offline') {
                                for($i=1; $i <= $event['qty']; $i++) {
                                    $qrCode = self::generateQr();
                                    TransactionTicketQr::create([
                                        'transaction_detail_id' => $detailValue->id,
                                        'detail_event_id' => $detailValue->detail_event_id,
                                        'qr_code' => $qrCode,
                                        'qr_used' => false,
                                    ]);

                                    $event['qr_code'] = $qrCode;
                                    $onlineTicket[] = $event;
                                }

                            } else {
                                $onlineTicket[] = $event;
                            }
                        }

                    } else if($detailValue->transaction_type == 'membership') {
                        $membershipDuration = MembershipDuration::select([
                                'membership_duration.type',
                                'membership_duration.duration',
                                'membership_duration.membership_plan_id',
                                'membership_plan.plan_name',
                                'membership_plan.plan_code',
                                'membership_plan.limit_inquiry',
                                'membership_plan.allow_all_apps',
                            ])->join('membership_plan', 'membership_plan.id', 'membership_duration.membership_plan_id')
                            ->where('membership_duration.id', $detailValue->membership_duration_id)
                            ->first()->toArray();

                        $durationType = str_replace('ly', '', $membershipDuration['type']);
                        $membershipExpTime = date('Y-m-d', strtotime('+' . $membershipDuration['duration'] . ' ' . $durationType));

                        $user = User::find($transaction['user_id']);
                        $user->status = $membershipDuration['plan_code'];
                        $user->membership_start = date('Y-m-d');
                        $user->membership_exp = $membershipExpTime;
                        $user->membership_plan_id = $membershipDuration['membership_plan_id'];
                        $user->save();

                        $contentExclusive = [];
                        $membershipExclusive = MembershipExclusive::where('membership_plan_id', $membershipDuration['membership_plan_id'])
                            ->orderBy('id', 'DESC')->get()->toArray();
                        foreach($membershipExclusive as $value) {
                            if($value['type'] == 'event') {
                                $ticket = DetailEvent::select([
                                    'event.master_category_id',
                                    'event.master_app_id',
                                    'event.type',
                                    'event.title',
                                    'event.thumbnail_image',
                                    'detail_event.ticket_pass',
                                    'detail_event.price',
                                    'master_category.category_name',
                                ])
                                    ->join('event', 'event.id', 'detail_event.event_id')
                                    ->join('master_category', 'master_category.id', 'event.master_category_id')
                                    ->where('detail_event.id', $value['content_id'])
                                    ->first()->toArray();
                            } else {
                                $course = OnlineCourse::find($value['content_id'], ['price', 'title', 'image'])->toArray();
                            }

                            TransactionDetail::create([
                                'transaction_id' => $transaction['id'],
                                'qty' => $value['type'] == 'online-course' ? null : $value['qty'],
                                'detail_event_id' => $value['type'] == 'event' ? $value['content_id'] : null,
                                'online_course_id' => $value['type'] == 'online-course' ? $value['content_id'] : null,
                                'master_category_id' => isset($ticket) ? $ticket['master_category_id'] : null,
                                'transaction_type' => $value['type'],
                                'master_app_id' => isset($ticket) ? $ticket['master_app_id'] : null,
                                'online' => $value['type'] == 'online-course' || (isset($ticket) && $ticket['type'] == 'online'),
                                'ticket_pass' => isset($ticket) && $ticket['ticket_pass'],
                                'price' => $value['type'] == 'online-course' ? $course['price'] : $ticket['price'],
                            ]);

                            if(isset($ticket)) {
                                $value['type'] = $ticket['category_name'];
                                $value['title'] = $ticket['title'];
                                $value['price'] = $ticket['price'];
                                $value['thumbnail_image'] = $ticket['thumbnail_image'];
                            } else {
                                $value['type'] = 'Online Course';
                                $value['title'] = $course['title'];
                                $value['price'] = $course['price'];
                                $value['thumbnail_image'] = $course['image'];
                            }

                            $contentExclusive[] = $value;
                        }

                        $user = $user->toArray();
                        $notifMail = new NotifMembershipMail($user, $membershipDuration, $contentExclusive);
                        Mail::to($user['email'])->send($notifMail);
                        break;

                    } else if($detailValue->transaction_type == 'online-course') {
                        $selectCourse = [
                            'online_course.title',
                            'online_course.image AS cover_image',
                            'users.email AS customer_email',
                            DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS customer_name"),
                            'transaction_detail.price',
                            'transaction_detail.online_course_id',
                            'transaction.order_id',
                            'instructor.name AS instructor_name',
                            'online_course.video_length',
                        ];

                        $onlineCourse = TransactionDetail::select($selectCourse)
                            ->join('online_course', 'online_course.id', 'transaction_detail.online_course_id')
                            ->join('mapping_instructor', 'mapping_instructor.online_course_id', 'online_course.id')
                            ->join('instructor', 'instructor.id', 'instructor_id')
                            ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
                            ->join('users', 'users.id', 'transaction.user_id')
                            ->where('transaction_detail.id', $detailValue->id)
                            ->first()->toArray();

                        unset($onlineCourse['promo_price']);
                        $onlineCourse['url_course'] = env('FRONT_APP_URL');
                        $onlineCourse['duration'] = Helper::formatDuration( Helper::getDuration($onlineCourse['video_length']) );
                        $onlineTicket[] = $onlineCourse;
                    }
                }

                if(count($onlineTicket) > 0) {
                    $customerEmail = $onlineTicket[0]['customer_email'];
                    $notifMail = new NotifTransaksiMail($onlineTicket);
                    Mail::to($customerEmail)->send($notifMail);

                    $orderId = str_replace('/', '_', $onlineTicket[0]['order_id']);
                    $attachNotifMail = storage_path('tmp/' . $orderId . '.pdf');
                    if(file_exists($attachNotifMail)) @unlink($attachNotifMail);
                }
            }

            switch($payment_progress) {
                case 'success':
                    $titleNotif = 'Pembelian Berhasil';
                    $messageNotif = 'berhasil di konfirmasi.';
                    break;

                case 'failed':
                    $titleNotif = 'Pembelian Gagal';
                    $messageNotif = 'gagal di konfirmasi.';
                    break;

                case 'expired':
                    $titleNotif = 'Pembelian Gagal';
                    $messageNotif = 'gagal di konfirmasi, pembayaran telah expired.';
                    break;

                default:
                    $titleNotif = '';
                    $messageNotif = '';
            }

            Notification::create([
                'user_id' => $transaction['user_id'],
                'transaction_id' => $transaction_id,
                'title' => $titleNotif,
                'content' => "Pembelian Anda dengan No Order {$transaction['order_id']}\" {$messageNotif}",
                'is_read' => false,
            ]);

            DB::commit();
            // Helper::setRedis('status_order_id_' . $order_id . '_' . $ip, json_encode($request->all()), $this->ttlFiveDay);
            $text = "0,Success,$reconcile_id,$order_id,$date";
            $response = Response::make($text, 200);
            $response->header('Content-Type', 'text/plain');
            return $response;

        // } catch (\Exception $e) {
        //     $message = $e->getMessage();
        //     DB::rollback();
        //     $text = "1,$message,$reconcile_id,$order_id,$date";
        //     $response = Response::make($text, 200);
        //     $response->header('Content-Type', 'text/plain');
        //     return $response;
        // }
    }

    public function generateQr()
    {
        $number = rand(1, 100000);
        $micro_date = microtime();
        $date_array = explode(" ", $micro_date);
        $microtime = str_replace(':', '', date('h:i:s')) . str_replace('.', '', $date_array[0]);
        return $microtime . $number;
    }

    public function listTransaction(Request $request)
    {
        // dd("hello");
        $message = 'Daftar Transaksi';
        $user = $request->get('session_user');
        $user_id = $user['id'];

        $start = $request->get('start') ?? 0;
        $limit = $request->get('limit') ?? 10;
        $payment_progress = $request->get('payment_progress');

        $select = [
            'transaction.id',
            'transaction.order_id',
            'transaction.price',
            'transaction.fee_pg',
            'transaction.total_amount',
            'transaction.payment_progress',
            'transaction.created_at',
            'transaction_detail.transaction_type',
            'master_app.app_name',
        ];

        $transactionQuery = Transaction::select($select)
            ->join('transaction_detail', 'transaction.id', '=', 'transaction_detail.transaction_id')
            ->join('master_app', 'transaction_detail.master_app_id', '=', 'master_app.id')
            ->where('transaction.user_id', $user_id);
        
        // dd($transactionQuery);

        if ($payment_progress) {
            $transactionQuery = $transactionQuery->where('transaction.payment_progress', $payment_progress);
        }

        $transactions = $transactionQuery->limit($limit)
            ->offset($start)
            ->orderBy('transaction.updated_at', 'DESC')
            ->get()
            ->toArray();
            // dd($transactions);

        if (empty($transactions)) {
            return $this->sendResponse(result: [], message: $message);
        }

        $transactions = array_map(function($transaction) {
            $transaction['price'] = number_format($transaction['price'], 2, '.', '');
            $transaction['total_amount'] = number_format($transaction['total_amount'], 2, '.', '');
            $transaction['total_fee'] = TransactionFee::where('transaction_id', $transaction['id'])->sum('fee');
            $transaction['created_at'] = Helper::timestampToDateTime($transaction['created_at']);
            $transaction['total_items'] = TransactionDetail::where('transaction_id', $transaction['id'])->count();
            return $transaction;
        }, $transactions);

        return $this->sendResponse(result: $transactions, message: $message);
    }

    public function detailOnlineCourse($transaction){
        $selectDetail = [
            'detail_event_id',
            'ticket_pass_id',
            'online_course_id',
        ];
        $selectInstructor =  ['instructor.name','instructor.title'];

        $transactionDetail = TransactionDetail::select($selectDetail)
            ->where('transaction_id', $transaction['id'])->first()->toArray();

        $transaction['product'] = $transactionDetail;
        $detailOnlineCourse = (new OnlineCourseController())->detailWithRating($transactionDetail['online_course_id']);

        if($detailOnlineCourse !== null) {
            $transaction['product']['image'] = $detailOnlineCourse['image'];
            $transaction['product']['title'] = $detailOnlineCourse['title'];
            $transaction['product']['rating'] = $detailOnlineCourse['rate'];
            $transaction['product']['rating_count'] = $detailOnlineCourse['rating_count'];
            $transaction['product']['duration'] = $detailOnlineCourse['duration'];
        }

        $getInstructor = MappingInstructor::select($selectInstructor)
            ->leftJoin('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id')
            ->where('mapping_instructor.online_course_id',$transactionDetail['online_course_id'])
            ->groupBy('instructor.name','instructor.title')->first()->toArray();
        $transaction['product']['instructor'] = $getInstructor;

        unset($instructor);
        unset($duration);
        unset($hour);
        unset($minute);
        unset($getInstructor);

        return $transaction;
    }

    public function detailEvent($transaction){
        $selectDetail = [
            'detail_event_id',
            'ticket_pass_id',
            'online_course_id',
        ];

        $transactionDetail = TransactionDetail::select($selectDetail)
            ->where('transaction_id', $transaction['id'])->first()->toarray();
        $transaction['product'] = $transactionDetail;

        $detailEvent = (new EventController())->detailWithRating($transactionDetail['detail_event_id']);
        if($detailEvent) {
            $transaction['product']['image'] = $detailEvent['image'];
            $transaction['product']['title'] = $detailEvent['title'];
            $transaction['product']['rating'] = $detailEvent['rate'];
            $transaction['product']['rating_count'] = $detailEvent['rating_count'];
        }

        $duration = null;
        if($transaction['ticket_pass']){
            $transactionDetailBulk = TransactionDetail::select($selectDetail)
                ->where('transaction_id', $transaction['id'])->get()->toarray();
            $passEventId = array_column($transactionDetailBulk, 'ticket_pass_id');

            $date = [];
            $count_seconds = 0;

            $dateTransactionDetail = DetailEvent::select('date','start_time','end_time')
                ->whereIn('id', $passEventId)
                ->orderBy('date', 'ASC')
                ->get()->toarray();
            foreach ($dateTransactionDetail as $to => $items){
                $extract_date=$items['date'].' '.$items['start_time'];
                $extract_end_date=$items['date'].' '.$items['end_time'];
                $from_time = strtotime($extract_date);
                $to_time = strtotime($extract_end_date);
                $count_seconds += abs($to_time - $from_time);
                $date[] = $items['date'];
            }

            $duration = Helper::getDuration($count_seconds);
            $transaction['product']['date'] = $date;
            unset($date);
            unset($transactionDetailBulk);
            unset($dateTransactionDetail);

        } else {
            $extract_date = $detailEvent['date'].' '.$detailEvent['start_time'];
            $extract_end_date = $detailEvent['date'].' '.$detailEvent['end_time'];
            $from_time = strtotime($extract_date);
            $to_time = strtotime($extract_end_date);

            $duration = Helper::getDuration( abs($to_time - $from_time) );
            $transaction['product']['date'] = [$detailEvent['date']];
        }

        $transaction['product']['duration'] = Helper::formatDuration($duration);
        unset($duration);
        unset($hour);
        unset($minute);
        unset($getInstructor);

        return $transaction;
    }
    public function detailMembership($transaction) {
        $selectProduct = [
            'membership_duration.membership_plan_id',
            'transaction_detail.membership_duration_id',
            'membership_plan.plan_name',
            'membership_duration.name AS duration_name',
            'membership_duration.type AS duration_type',
            'membership_duration.duration',
        ];

        $product = TransactionDetail::select($selectProduct)
            ->leftJoin('membership_duration', 'transaction_detail.membership_duration_id', '=', 'membership_duration.id')
            ->leftJoin('membership_plan', 'membership_duration.membership_plan_id', '=', 'membership_plan.id')
            ->where('transaction_detail.transaction_id', $transaction['id'])
            ->first()->toArray();

        $transaction['product'] = $product;

        return $transaction;
    }
    public function detailTransaction(Request $request){
        try {
            $user = $request->get('session_user');
            $user_id = $user['id'];
            $order_id = $request->get('order_id');
            $message = "Detail transaksi $order_id";

            $select = [
                'transaction.id',
                'transaction.voucher_number',
                'transaction.payment_progress',
                'transaction.payment_method',
                'transaction.order_id',
                'transaction.price',
                'transaction.fee_pg',
                'transaction.total_amount',
                'transaction.discount_type',
                'transaction.discount',
                'transaction.created_at'
            ];
            $transaction = Transaction::select($select)
                ->where('transaction.user_id', $user_id)
                ->where('transaction.order_id', $order_id)->firstOrFail()->toArray();

            if($transaction['transaction_type']=='event'){
                $selectDetail = [
                    'transaction_detail.detail_event_id',
                    'transaction_detail.ticket_pass_id',
                    'transaction_detail.online_course_id',
                    'detail_event.date',
                    'detail_event.start_time',
                    'detail_event.end_time',
                    'transaction_detail.qr_code',
                    'transaction_detail.qty',
                ];

                $result = self::detailEvent($transaction);
                // $transactionDetail = TransactionDetail::select($selectDetail)->where('transaction_detail.transaction_id', $transaction['id']);

                // if(!$transaction['ticket_pass']){
                //     $transactionDetail =  $transactionDetail->join('detail_event','transaction_detail.detail_event_id','=','detail_event.id');
                // }else{
                //     $transactionDetail = $transactionDetail->join('detail_event','transaction_detail.ticket_pass_id','=','detail_event.id');
                // }

                // $transactionDetail = $transactionDetail->join('event','detail_event.event_id','=','event.id');
                // $transactionDetail= $transactionDetail->where('transaction_detail.transaction_id', $transaction['id'])->get()->toarray();
                // foreach ($transactionDetail as $key => $value) {
                //     $qr_code = $value['qr_code'];
                //     $transactionDetail[$key]['qr_image'] = $qr_code ? "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$qr_code&choe=UTF-8" : '';
                //     unset($qr_code);
                // }

                // $result['transaction_detail'] = $transactionDetail;

            } else if($transaction['transaction_type']=='online-course'){
                $selectDetail = [
                    'transaction_detail.detail_event_id',
                    'transaction_detail.ticket_pass_id',
                    'transaction_detail.online_course_id',
                ];

                $result = self::detailOnlineCourse($transaction);
                // $transactionDetail = TransactionDetail::select($selectDetail)
                //     ->leftJoin('online_course','transaction_detail.online_course_id','=','online_course.id')
                //     ->where('transaction_detail.transaction_id', $transaction['id'])->get()->toarray();
                // $result['transaction_detail'] = $transactionDetail;

            } else if($transaction['transaction_type']=='membership'){
                $selectDetail = [
                    'transaction_detail.membership_duration_id',
                    'transaction_detail.membership_plan_id',
                ];

                // $transactionDetail = TransactionDetail::select($selectDetail)
                //     ->leftJoin('membership_duration','transaction_detail.membership_duration_id','=','membership_duration.id')
                //     ->where('transaction_detail.transaction_id', $transaction['id'])
                //     ->get()->toarray();

                $result = self::detailMembership($transaction);
                // $result['transaction_detail'] = $transactionDetail;
            }

            $result['created_at'] = Helper::timestampToDateTime($result['created_at']);
            return $this->sendResponse(result: $result, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Transaksi Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function detailTransactionV2(Request $request) {
        try {
            $user = $request->get('session_user');
            $user_id = $user['id'];
            $order_id = $request->get('order_id');
            $message = "Detail transaksi $order_id";

            $transaction = Transaction::select('price')
                ->where('transaction.user_id', $user_id)
                ->where('transaction.order_id', $order_id)->first()->price;
		
            if((int)$transaction > 0 ){
                $select = [
                    'transaction.id',
                    'transaction.voucher_number',
                    'transaction.payment_progress',
                    'transaction.payment_method',
                    'transaction.order_id',
                    'transaction.price',
                    'transaction.fee_pg',
                    'transaction.total_amount',
                    'transaction.discount_type',
                    'transaction.discount',
                    'transaction.created_at',
                    'transaction_xendit.bank_code'
                ];
                $transaction = Transaction::leftJoin('transaction_xendit','transaction.external_id','transaction_xendit.external_id')
                    ->select($select)
                    ->where('transaction.user_id', $user_id)
                    ->where('transaction.order_id', $order_id)->firstOrFail()->toArray();
            }else{
                $select = [
                    'transaction.id',
                    'transaction.voucher_number',
                    'transaction.payment_progress',
                    'transaction.payment_method',
                    'transaction.order_id',
                    'transaction.price',
                    'transaction.fee_pg',
                    'transaction.total_amount',
                    'transaction.discount_type',
                    'transaction.discount',
                    'transaction.created_at'
                ];
                $transaction = Transaction::select($select)
                    ->where('transaction.user_id', $user_id)
                    ->where('transaction.order_id', $order_id)->firstOrFail()->toArray();
            }

            $selectDetail = [
                'id',
                'detail_event_id',
                'online_course_id',
                'membership_duration_id',
                'transaction_type',
                'qty',
                'online',
                'ticket_pass',
                'price',
            ];

            $transactionDetail = TransactionDetail::select($selectDetail)
                ->where('transaction_id', $transaction['id'])
                ->orderBy('id', 'ASC')->get()->toarray();

            $transactionDetail = array_map( function($value) use($transaction) {
                if($value['transaction_type'] == 'online-course') {
                    $detailOnlineCourse = (new OnlineCourseController())->detailWithRating($value['online_course_id']);
                    $value['product'] = $detailOnlineCourse;

                } else if($value['transaction_type'] == 'event') {
                    $detailEvent = (new EventController())->detailWithRating($value['detail_event_id']);
                    $value['product'] = $detailEvent;

                    if($value['ticket_pass']){
                        $count_seconds = 0;
                        $ticketPassItem = [];
                        $ticketPassId = DetailEvent::find($value['detail_event_id'], ['ticket_pass_id']);
                        $ticketPassId = json_decode($ticketPassId->ticket_pass_id, true);

                        foreach($ticketPassId as $valuePassId) {
                            $ticketPassData = (new EventController())->detailWithRating($valuePassId);
                            $ticketPassItem[] = $ticketPassData;

                            $extract_date=$ticketPassData['date'].' '.$ticketPassData['start_time'];
                            $extract_end_date=$ticketPassData['date'].' '.$ticketPassData['end_time'];
                            $from_time = strtotime($extract_date);
                            $to_time = strtotime($extract_end_date);
                            $count_seconds += abs($to_time - $from_time);
                        }

                        $dateTransactionDetail = DetailEvent::select('date','start_time','end_time')
                            ->join('transaction_ticket_pass', 'transaction_ticket_pass.detail_event_id', 'detail_event.id')
                            ->where('transaction_ticket_pass.transaction_id', $transaction['id'])
                            ->orderBy('date', 'ASC')->get()->toarray();

                        foreach ($dateTransactionDetail as $to => $items){
                            $extract_date=$items['date'].' '.$items['start_time'];
                            $extract_end_date=$items['date'].' '.$items['end_time'];
                            $from_time = strtotime($extract_date);
                            $to_time = strtotime($extract_end_date);
                            $count_seconds += abs($to_time - $from_time);
                        }

                        $duration = Helper::formatDuration( Helper::getDuration($count_seconds) );
                        $value['product']['start_time'] = $dateTransactionDetail[0]['start_time'];
                        $value['product']['end_time'] = $dateTransactionDetail[0]['end_time'];

                        $value['ticket_pass_item'] = $ticketPassItem;
                        unset($dateTransactionDetail);

                    } else {
                        $extract_date = $detailEvent['date'].' '.$detailEvent['start_time'];
                        $extract_end_date = $detailEvent['date'].' '.$detailEvent['end_time'];
                        $from_time = strtotime($extract_date);
                        $to_time = strtotime($extract_end_date);

                        $value['ticket_pass_item'] = [];
                        $duration = Helper::formatDuration(Helper::getDuration( abs($to_time - $from_time) ));
                        // $transaction['product']['date'] = [$detailEvent['date']];
                    }

                    $value['product']['duration'] = $duration;
                    $qrCode = [];

                    if($transaction['payment_progress'] == 'success') {
                        $qrCode = TransactionTicketQr::select(['qr_code'])
                            ->where('transaction_detail_id', $value['id'])->get()->pluck('qr_code')->toArray();
                    }

                    $value['url_meeting'] = $value['product']['url_meeting'];
                    unset($value['product']['url_meeting']);
                    $value['qr_code'] = $qrCode;

                } else if($value['transaction_type'] == 'membership') {
                    $selectProduct = [
                        'membership_plan.id AS membership_plan_id',
                        'membership_plan.plan_name',
                        'membership_plan.thumbnail_image',
                        'membership_duration.name AS duration_name',
                        'membership_duration.type AS duration_type',
                        'membership_duration.duration',
                    ];

                    $product = TransactionDetail::select($selectProduct)
                        ->leftJoin('membership_duration', 'transaction_detail.membership_duration_id', '=', 'membership_duration.id')
                        ->leftJoin('membership_plan', 'membership_duration.membership_plan_id', '=', 'membership_plan.id')
                        ->where('transaction_detail.id', $value['id'])
                        ->first()->toArray();

                    $product['title'] = MembershipDuration::getDurationNameFormat($product['duration_type'], $product['duration'], 'ENG');
                    $product['duration'] = strval($product['duration']);
                    $value['product'] = $product;
                }
                $value['price'] = number_format($value['price'],2, '.', '');
                return $value;
            }, $transactionDetail);

            if(is_array($transactionDetail[0]) && $transactionDetail[0]['transaction_type'] == 'membership' && $transaction['payment_progress'] == 'success') {
                $transactionDetail[0]['content_exclusive'] = array_splice($transactionDetail, 1);
                $transactionDetail[0]['voucher'] = MembershipVoucher::select([
                    'membership_voucher.id',
                    'voucher.name',
                    'voucher.description',
                    'voucher.discount',
                    'voucher.type',
                    'voucher.qty',
                    'voucher.limit',
                    'voucher.max_discount',
                    'voucher.voucher_number',
                    'voucher.start_date',
                    'voucher.exp_date',
                ])
                    ->join('voucher', 'voucher.id', 'membership_voucher.voucher_id')
                    ->where('membership_voucher.membership_plan_id', $transactionDetail[0]['product']['membership_plan_id'])
                    ->where('status', 'active')->orderBy('membership_voucher.id', 'DESC')->get()->toArray();

                $transactionDetail[0]['voucher'] = array_map( function($value) {
                    $value['discount'] = $value['type'] == 'percent' ? $value['discount'] . '%' : 'Rp. ' . number_format($value['discount'], 0, ',', '.');
                    unset($value['type']);
                    return $value;
                }, $transactionDetail[0]['voucher']);

                $transactionDetail = [$transactionDetail[0]];
            }

            $transaction['detail_transaction'] = $transactionDetail;
            $selectFee = [
                'master_transaction_fee.title',
                'transaction_fee.fee',
                'transaction_fee.fee_type',
            ];

            $transactionFee = TransactionFee::select($selectFee)
                ->join('master_transaction_fee', 'master_transaction_fee.id', 'transaction_fee.master_transaction_fee_id')
                ->where('transaction_id', $transaction['id'])->get();

            $transaction['price'] = number_format($transaction['price'],2, '.', '');
            $transaction['total_amount'] = number_format($transaction['total_amount'],2, '.', '');
            $transaction['total_fee'] = $transactionFee->sum('fee');
            $transaction['fee_detail'] = $transactionFee->toArray();
            $transaction['created_at'] = Helper::timestampToDateTime($transaction['created_at']);
            return $this->sendResponse(result: $transaction, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Transaksi Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function checkout(Request $request)
    {
        try {
            $user = $request->get('session_user');
            $voucherNumber = $request->get('voucher_number');
            $membershipDurationId = $request->get('membership_duration_id');
            $today = date('Y-m-d');
            if(!empty($membershipDurationId)) {
                $membership = MembershipDuration::select([
                    'membership_duration.id',
                    'membership_plan.id AS membership_plan_id',
                    'membership_plan.plan_name',
                    'membership_plan.thumbnail_image',
                    'membership_duration.name AS duration_name',
                    'membership_duration.type AS duration_type',
                    'membership_duration.duration',
                    'membership_duration.price',
                ])
                    ->join('membership_plan', 'membership_plan.id', 'membership_duration.membership_plan_id')
                    ->where('membership_duration.id', $membershipDurationId)
                    ->firstOrFail()->toArray();

                $cartItems = [
                    [
                        'id' => CartV2::orderBy('id', 'desc')->first()->id ?? 1,
                        'user_id' => $user['id'],
                        'qty' => 1,
                        'type' => 'membership',
                        'content_id' => $membershipDurationId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                ];

                $membership['title'] = MembershipDuration::getDurationNameFormat($membership['duration_type'], $membership['duration'], 'ENG');
                $cartItems[0]['data'] = $membership;
                $discount = 0;
                $totalPrice = $membership['price'];

            } else {
                $cartItems = CartV2::where('user_id', $user['id'])->orderBy('id', 'desc')->get()->toArray();
                if(count($cartItems) == 0) {
                    return $this->sendError('Keranjang Anda kosong.', code: 400);
                }

                $totalPrice = 0;
                $discount = 0;
                $cartItems = array_map( function($value) use(&$totalPrice, &$discount) {
                    if($value['type'] == 'online-course') {
                        // online course
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
                            $onlineCourse->description = Helper::shortDescription($onlineCourse->description);
                            $onlineCourse->duration = Helper::formatDuration( Helper::getDuration($onlineCourse->duration) );

                            $selectInstructor = [ 'instructor.id','instructor.name','instructor.title','instructor.description','instructor.image'];
                            $instructor = MappingInstructor::select($selectInstructor)
                                ->join('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id')
                                ->where('mapping_instructor.online_course_id', $value['content_id'])->first()->toArray();

                            $onlineCourse->instructor = $instructor;
                        }

                        $value['data'] = $onlineCourse !== null ? $onlineCourse->toArray() : [];
                        $discount += $onlineCourse->promo_price > 0 ? $onlineCourse->promo_price : 0;
                        $totalPrice += $onlineCourse->promo_price > 0 ? $onlineCourse->promo_price : $onlineCourse->price;

                    } else {
                        // event
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

                        $value['data'] = $event !== null ? $event->toArray() : [];
                        $discount += $event->promo_price > 0 ? $event->promo_price : 0;
                        $price =  $event->price;
                        $totalPrice += $price * $value['qty'];
                    }
                    return $value;
                }, $cartItems);
            }

            if(!empty($voucherNumber)) {
                $checkVoucher = self::voucherAvailable($voucherNumber, $today);
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
                $calculateDiscount = self::calculateDiscount($checkVoucher, $totalPrice);
                $amount = $calculateDiscount['amount'];
                $discount = $calculateDiscount['discount'];

            } else {
                $amount = $discount > 0 ? $totalPrice - $discount : $totalPrice ;
                $discount = $discount ?? 0;
            }

            $totalFee = 0;
            $transactionFee = [];

            if($totalPrice > 0) {
                $transactionFee = MasterTransactionFee::select(['title', 'fee', 'fee_type'])
                    ->where('status', 'publish')->orderBy('id', 'ASC')->get();
                $transactionFee = $transactionFee->map( function($value) use(&$totalFee, $totalPrice) {
                    if($value->fee_type == 'percent') {
                        $value->fee = floor(($totalPrice / 100) * $value->fee);
                    }

                    $totalFee += $value->fee;
                    return $value;
                });

                $transactionFee = $transactionFee->toArray();
            }
            $result = [
                'total_price' => $totalPrice,
                'total_fee' => $totalFee,
                'total_amount' => $amount + $totalFee,
                'discount' => $discount,
                'discount_type' => $checkVoucher['type'] ?? null,
                'fee_detail' => $transactionFee,
                'items' => $cartItems
            ];

            return $this->sendResponse(result: $result, message: 'Transaksi Checkout Berhasil');

        } catch( ModelNotFoundException $e){
            return $this->sendError(error: 'Barang Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            DB::rollback();
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function downloadTicket($id, Request $request)
    {
            $user = $request->get('session_user');

            $select = [
                'event.title',
                'master_category.category_name',
                'event.cover_image',
                'event.address',
                'event.type',
                'users.email AS customer_email',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS customer_name"),
                'detail_event.url_meeting',
                'detail_event.date',
                'detail_event.start_time',
                'detail_event.end_time',
                'detail_event.price',
                'transaction_detail.detail_event_id',
                'transaction_detail.qty',
                'transaction.order_id',
                'detail_event.ticket_pass',
                'detail_event.ticket_pass_id',
            ];

            $event = TransactionDetail::select($select)
                ->join('detail_event', 'detail_event.id', 'transaction_detail.detail_event_id')
                ->join('event', 'event.id', 'detail_event.event_id')
                ->join('master_category', 'master_category.id', 'event.master_category_id')
                ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
                ->join('users', 'users.id', 'transaction.user_id')
                ->where('transaction_detail.id', $id)
                ->where('transaction.payment_progress', 'success')
                ->where('transaction.user_id', $user['id'])
                ->firstOrFail()->toArray();
            if($event['ticket_pass']) {
                for($i=1; $i <= $event['qty']; $i++) {
                    $ticketPassId = json_decode($event['ticket_pass_id'], true);
                    $eventPass = TransactionTicketQr::select(['title', 'url_meeting', 'date', 'start_time', 'end_time', 'qr_code'])
                        ->join('detail_event', 'detail_event.id', 'transaction_ticket_qr.detail_event_id')
                        ->whereIn('transaction_ticket_qr.detail_event_id', $ticketPassId)->get()->toArray();

                    $event['ticket_pass_item'] = $eventPass;
                    $onlineTicket[] = $event;

                    if($event['type'] == 'offline') {
                        $event['ticket_pass'] = false;
                        $event['ticket_pass_id'] = null;
                        unset($event['ticket_pass_item']);

                        foreach($eventPass as $valueTicketPass) {
                            $event['url_meeting'] = $valueTicketPass['url_meeting'];
                            $event['date'] = $valueTicketPass['date'];
                            $event['start_time'] = $valueTicketPass['start_time'];
                            $event['end_time'] = $valueTicketPass['end_time'];
                            $event['qr_code'] = $valueTicketPass['qr_code'];
                            $onlineTicket[] = $event;
                        }
                    }
                }

            } else {
                if($event['type'] == 'offline') {
                    $transactionTicketQr = TransactionTicketQr::select(['qr_code'])
                        ->where('transaction_detail_id', $id)
                        ->where('detail_event_id', $event['detail_event_id'])->get()->toArray();

                        foreach($transactionTicketQr as $value) {
                        $event['qr_code'] = $value['qr_code'];
                        $onlineTicket[] = $event;
                    }
                } else {
                    $onlineTicket[] = $event;
                }
            }
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'fontDir' => [
                    storage_path('pdf-fonts'),
                ],
                'fontdata' => [
                    'kanit' => [
                        'R' => 'Kanit-Regular.ttf',
                        'I' => 'Kanit-Italic.ttf',
                        'B' => 'Kanit-Bold.ttf',
                        'BI' => 'Kanit-BoldItalic.ttf',
                    ]
                ],
                'default_font' => 'kanit',
		'tempDir' => __DIR__ . '/custom/temp/dir/path',
		'curlAllowUnsafeSslRequests' => true
            ]);
            foreach($onlineTicket as $valueTicket) {
                $view = view('mail/e-ticket')->with(['onlineTicket' => $valueTicket]);
                $mpdf->AddPage('auto');
                $mpdf->WriteHTML($view);
            }

            $orderId = str_replace('/', '_', $event['order_id']);
            $mpdf->OutputHttpDownload("E-Ticket {$orderId}_{$id}.pdf");
    }
}
