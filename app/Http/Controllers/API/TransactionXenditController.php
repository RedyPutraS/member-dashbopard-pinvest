<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\App;
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
use App\Models\TransactionXendit;
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
use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\API\TransactionController;
use DateTimeZone;
class TransactionXenditController extends BaseController
{

    protected $urlPaymentXendit;
    protected $bank_fee;
    protected $va_code;
    protected $bank_image;
    protected $ttlFiveDay;

    public function __construct()
    {
        $this->urlPaymentXendit = env('XENDIT_URL');
        $this->ttlFiveDay = 432000;
        $this->bank_fee = [
            '002' => 4500,
            '008' => 4500,
            '009' => 4500,
            '011' => 4500,
            '013' => 4500,
            '014' => 4500,
            '016' => 4500,
            '022' => 4500,
            '057' => 4500,
            '098' => 4500,
            '071' => 4500,
            '043' => 4500,
            '029' => 4500,
            '032' => 4500,
        ];
        $this->va_code = [
            'BRI' => '002',
            'MANDIRI'=> '008',
            'BNI'=>'009',
            'DANAMON' => '011',
            'PERMATA'=> '013',
            'BCA' => '014',
            'BII' => '016',
            'CIMB' =>'022',
            'SAHABAT_SAMPOERNA' => '098',
            'BSI' => '071',
            'BJB' => '043',
            'DBS' => '029',
            'BNC' => '032',
        ];
        $this->bank_image = [
            '002' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+BRI.png',
            '008' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+Mandiri.png',
            '009' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+BNI.png',
            '011' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+Danamon.png',
            '013' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+Permata.png',
            '014' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+BCA.png',
            '016' => 'https://kampus-kita.oss-ap-southeast-5.aliyuncs.com/public/asset/images/bii.jpg',
            '022' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+Cimb+Niaga.png',
            '098' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+Sahabat+Sempurna.png',
            '071' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+BSI.png',
            '043' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+BJP.png',
            '029' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+DBS.png',
            '032' => 'https://pinvestbucket.s3.ap-southeast-2.amazonaws.com/Bank+BNC.png',
        ];
    }
    public static function getSecretKey(){
        $secret_key = env('SECRET_KEY_XENDIT');
        $pass_seckey = env('PASSWORD_SECRET_KEY');
        $secret_text = $secret_key.":";
        $secretBase64 = base64_encode($secret_text);
        return $secretBase64;
    }


    public function listBankXendit()
    {
        $prefix = 'available_virtual_account_banks';
        $message = 'Daftar Bank VA';
        $getKey = $this->getSecretKey();
        // $checkRedis = Helper::getRedis($prefix);
        // $checkRedis = false;
        
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // // dd($getRedis);
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }
        // dd($getKey);
        $payloadXendit = Http::withHeaders(['Authorization' => 'Basic '. $getKey])->get($this->urlPaymentXendit . 'available_virtual_account_banks');
        // dd('Basic '. $getKey, $this->urlPaymentXendit . 'available_virtual_account_banks');
        // $response = Http::asForm()->timeout(20)->post($this->urlPayment . '/rest/merchant/merchantinfo', $payloadXendit);
        $result = json_decode($payloadXendit->body(), true);
        $arrPanduanPg = file_get_contents( env('ESPAY_PANDUAN') );
        $arrPanduanPg = json_decode($arrPanduanPg, true);
        $listVa = [];
        foreach ($result as $key => $value) {
            if($value['is_activated'] === true){
                $value['name'];
                $value['code'];
                $value['bankCode'] = $this->va_code[ $value['code'] ] ?? '';
                $value['fee'] = $this->bank_fee[ $value['bankCode'] ] ?? '';
                $value['image'] = $this->bank_image[ $value['bankCode'] ] ?? '';
                $value['productCode'] = $value['code'] . 'ATM';
                $value['country'];
                $value['currency'];
                foreach($arrPanduanPg as $val) {
                    if( preg_match("/{$val['bankName']}/i", $value['code']) == 1 ) {
                        $value['intruction'] = $val['intruction'];
                        break;
                    }
                }
                $listVa[] = $value;
            }
        }

        return $this->sendResponse(result: $listVa, message: $message);
    }

    public function webhookSuccess(Request $request){
        $dataRaw = $request->all();
        
        if(isset($dataRaw["event"]) && ($dataRaw["event"] === "payment.succeeded" || $dataRaw["event"] === "payment.failed" || $dataRaw["event"] === "payment.cancelled")) {
            $saveToDB = new TransactionXendit();
            $saveToDB->updated_at = $dataRaw['data']['updated'] ?? null;
            $saveToDB->created_at = $dataRaw['data']['created'] ?? null;
            $saveToDB->payment_method_id = $dataRaw['data']['payment_request_id'] ?? null;
            $saveToDB->external_id = $dataRaw['data']['payment_method']['id'] ?? null;
            $saveToDB->account_number = $dataRaw['data']['payment_method']['virtual_account']['channel_properties']['virtual_account_number'] ?? null;
            $saveToDB->bank_code = $dataRaw['data']['payment_method']['virtual_account']['channel_code'] ?? null;
            $saveToDB->amount = $dataRaw['data']['payment_method']['virtual_account']['amount'] ?? null;
            $saveToDB->transaction_timestamp = $dataRaw['data']['payment_method']['created'] ?? null;
            $saveToDB->id_webhook = $dataRaw['data']['id'] ?? null;
            $saveToDB->status = $dataRaw['data']['status'] ?? null;
            $saveToDB->response = json_encode($dataRaw);
            $saveToDB->save();
            
            // Execute curl request to register callback URL with Xendit
            $url = 'https://api.xendit.co/callback_urls';
            $apiKey = 'xnd_development_X3vQIyi7szDAAjJIgmfDNu6k6CWCKQMMTc5cyO2nKPhw4c1QBxvQK40SH415BY2X';
            $headers = [
                'Content-Type: application/json',
            ];
            $data = [
                'url' => 'https://www.xendit.co/callback_catcher'
            ];
            $curl = curl_init();
            $payload = json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_USERPWD, $apiKey.":");
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($curl);
    
            // Check if curl request was successful
            if($result === false) {
                // Log or handle the error here
                $error = curl_error($curl);
                // Example: Log the error
                Log::error('Curl error: ' . $error);
            }
    
            // Close curl session
            curl_close($curl);
    
            // Update transaction payment progress if found
            $updateTransaction = Transaction::join('users','transaction.user_id','users.id')
						->select('transaction.*','users.email')
						->where('external_id', $saveToDB->external_id)->first();
            if($updateTransaction) {
                $updateTransaction->payment_progress = $saveToDB->status == 'SUCCEEDED' ? 'success' : 'booking';
                $updateTransaction->save();
            }
            
            $update_total_pembelian = User::find($updateTransaction->user_id);
            $update_total_pembelian->total_pembelian = $update_total_pembelian->total_pembelian + $dataRaw['data']['payment_method']['virtual_account']['amount'];
            $update_total_pembelian->save();

            if(User::find($updateTransaction->user_id)->total_pembelian >= 10000000 && User::find($updateTransaction->user_id)->status != 'Epic'){
                $update_membership = User::find($updateTransaction->user_id);
                $update_membership->status = 'Epic';
                $update_membership->membership_start = date('Y-m-d');
                $update_membership->membership_exp = date('Y-m-d', strtotime('+12 month'));
                $update_membership->membership_plan_id = 3;
                $update_membership->total_pembelian = 0;
                $update_membership->save();

                $update_membership = $update_membership->toArray();

                $membershipDuration = MembershipDuration::select([
                    'membership_duration.type',
                    'membership_duration.duration',
                    'membership_duration.membership_plan_id',
                    'membership_plan.plan_name',
                    'membership_plan.plan_code',
                    'membership_plan.limit_inquiry',
                    'membership_plan.allow_all_apps',
                ])->join('membership_plan', 'membership_plan.id', 'membership_duration.membership_plan_id')
                ->where('membership_duration.id', 14)
                ->first()->toArray();

                $contentExclusive = [];
                $membershipExclusive = MembershipExclusive::where('membership_plan_id', 3)
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

                    $transaction_detail_create = TransactionDetail::create([
                        'transaction_id' => $updateTransaction->id,
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
                        $qrCode = self::generateQrXendit();
                        TransactionTicketQr::create([
                            'transaction_detail_id' => $transaction_detail_create->id,
                            'detail_event_id' => $value['content_id'],
                            'qr_code' => $qrCode,
                            'qr_used' => false,
                        ]);
                    } else {
                        $value['type'] = 'Online Course';
                        $value['title'] = $course['title'];
                        $value['price'] = $course['price'];
                        $value['thumbnail_image'] = $course['image'];

                    }

                    $contentExclusive[] = $value;
                }

                $notifMail = new NotifMembershipMail($update_membership, $membershipDuration, $contentExclusive);
                Mail::to($update_membership['email'])->send($notifMail);
            }

            $transactionDetail = TransactionDetail::select(['id', 'transaction_type', 'membership_duration_id', 'detail_event_id'])
                ->where('transaction_id', $updateTransaction->id)->get();

            $onlineTicket = [];
	        $user_id = 0;
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
                                    $qrCode = self::generateQrXendit();
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
                                $qrCode = self::generateQrXendit();
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

                    $user = User::find($updateTransaction->user_id);
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

                        $transaction_detail_create = TransactionDetail::create([
                            'transaction_id' => $updateTransaction->id,
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
                            $qrCode = self::generateQrXendit();
                            TransactionTicketQr::create([
                                'transaction_detail_id' => $transaction_detail_create->id,
                                'detail_event_id' => $value['content_id'],
                                'qr_code' => $qrCode,
                                'qr_used' => false,
                            ]);
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
                $notifMail = new NotifTransaksiMail($onlineTicket);
                Mail::to($updateTransaction->email)->send($notifMail);
            }

            switch($dataRaw['data']['status']) {
                case 'SUCCEEDED':
                    $titleNotif = 'Pembelian Berhasil';
                    $messageNotif = 'berhasil di konfirmasi.';
                    break;

                default:
                    $titleNotif = '';
                    $messageNotif = '';
            }

            Notification::create([
                'user_id' => $updateTransaction->user_id,
                'transaction_id' => $updateTransaction->id,
                'title' => $titleNotif,
                'content' => "Pembelian Anda dengan No Order {$updateTransaction->order_id}\" {$messageNotif}",
                'is_read' => false,
            ]);

            return $this->sendResponse(result: $saveToDB, message: 'success');
        }
    }    

    public function webhookPaymentMethod(Request $request){
        return 'true';
    }

    public function updatePaymentExpire($id, Request $request){
        $getKey = $this->getSecretKey();
        $now =Carbon::now();
        $next_date = date("Y-m-d\TH:i:s\Z", strtotime($now .' +1 day'));
        $payload =[
            'virtual_account'=> [
                'channel_properties'=> [
                    'expires_at'=> $next_date
                ]
            ],
            'description'=> 'updated description'
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->urlPaymentXendit .'/v2/payment_methods/'.$request->id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array('Content-Type: application/json','Authorization: Basic '.$getKey),
        ));
        $response = curl_exec($curl);
        $results = json_decode($response);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        return $this->sendResponse(result: $results, message: 'success');
    }
    public function simulateVA(Request $request){

        $getKey = $this->getSecretKey();
        $payload =[
            'amount' => $request->amount
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->urlPaymentXendit .'/v2/payment_methods/'.$request->id.'/payments/simulate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array('Content-Type: application/json','Authorization: Basic '.$getKey),
        ));
        $response = curl_exec($curl);
        $results = json_decode($response);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        return $this->sendResponse(result: $results, message: 'success');
    }


    public function statusPaymentOrder(Request $request){
        $getKey = $this->getSecretKey();
        $order_id = Transaction::where('order_id', $request->get('order_id'))->first();
        if($order_id->order_id !== null) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $this->urlPaymentXendit .'/v2/payment_methods/'.$order_id->external_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array('Content-Type: application/json','Authorization: Basic '.$getKey),
            ));
            $response = curl_exec($curl);
            $results = json_decode($response);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $codeBank = $this->va_code[ $results->virtual_account->channel_code ];
	    
            $bank_logo = $this->bank_image[ $codeBank ];
            $utcDatetime = new DateTime($results->virtual_account->channel_properties->expires_at);
            $localTimezone = new DateTimeZone('UTC');
            $utcDatetime->setTimezone($localTimezone);
            $localDatetime = $utcDatetime->format('Y-m-d H:i:s');
            $results->expired_at = $localDatetime;
            $results->order_id = $order_id->order_id;
            $results->bank_image = $bank_logo;
            $results->intruction = [];
            if($results->status == 'SUCCESS'){
                $payment_progress = 'success';
                self::getStatusPaymentXendit($results->payment_progress, $order_id->order_id);
            }else if($results->status == 'FAILED'){
                $payment_progress = 'failed';
                self::getStatusPaymentXendit($payment_progress, $order_id->order_id);
            }else if($results->status == 'EXPIRED'){
                $payment_progress = 'expired';
		$order_id->delete();
		//self::getStatusPaymentXendit($payment_progress, $order_id->order_id);
            }else{
                $payment_progress = 'booking';
            }
            $results->payment_progress = $payment_progress;
            $arrPanduanPg = file_get_contents( env('ESPAY_PANDUAN') );
            $arrPanduanPg = json_decode($arrPanduanPg, true);

            foreach($arrPanduanPg as $val) {
                if( preg_match("/{$val['bankName']}/i", $results->virtual_account->channel_code) == 1 ) {
                    $results->intruction = $val['intruction'];
                    break;
                }
            }
        }else{
            $order_id->toarray();
            $results = json_decode($order_id);
        }

        return $this->sendResponse(result: $results, message: 'success');
    }

    public function createV3(Request $request)
    {
        // dd($request);
        // dd($request,$request->get('membership_duration_id'));
        $master_app = App::where('alias', $request->app)->first();
        $subTotal = $request->input('subTotal');
        $totalInCart = $request->input('totalInCart');
        $hargaPromo = $request->input('hargaPromo');
        $diskonFromCart = $request->input('diskon');
        $totalFromRequest = $request->input('total');
        // dd($master_app->id);
        $user = $request->get('session_user');
        if (empty($request->bank_code)) {
            return $this->sendError(error: 'Kode Bank diperlukan', code: 400);
        }

        if($request->get('type') == 'membership'){
            $cartItems = [
                [
                    'type' => 'membership',
                    'qty' => 1,
                    'content_id' => $request->get('id')
                ]
            ];
        }else{
            $membershipDurationId = $request->get('membership_duration_id');
            if(!empty($membershipDurationId)) {
                if($request->type == 'event'){
                    $cartItems = [
                        [
                            'type' => 'event',
                            'qty' => 1,
                            'content_id' => $membershipDurationId
                        ]
                    ];
                }else if ($request->type == 'online-course'){
                    $cartItems = [
                        [
                            'type' => 'online-course',
                            'qty' => 1,
                            'content_id' => $membershipDurationId
                        ]
                    ];
                }else{
                    $cartItems = [
                        [
                            'type' => 'membership',
                            'qty' => 1,
                            'content_id' => $membershipDurationId
                        ]
                    ];
                }
            } else {
                $cartItems = CartV2::where('user_id', $user['id'])->get()->toArray();
                if(count($cartItems) == 0) {
                    return $this->sendError('Keranjang Anda kosong.', code: 400);
                }
            }
        }
        
        DB::beginTransaction();
        $bank_code = $request->get('bank_code');
        $listBank = self::listBankXendit()->getOriginalContent()['data'];
        $getKey = $this->getSecretKey();
        $arrBankCode = array_column($listBank, 'bankCode');
        $searchBank = array_search($bank_code, $arrBankCode);
        if ($searchBank === false) {
            return $this->sendError(error: 'Metode Pembayaran tidak diizinkan');
        }
        $bank = $listBank[ $searchBank ];
        $voucher = $request->get('voucher');
        $type = $request->get('type') ?? 'event';
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
                        'detail_event.promo_price',
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

                    if ( !$product || (!$product->ticket_pass && strtotime(date('Y-m-d')) > strtotime($product->date)) ) {
                        return $this->sendError(
                            error: 'Event not found',
                            errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                        );
                    }

                    if($product['type'] == 'offline'){
                        $online = false;
                    }

                    $product = $product->toArray();
                    $price = $product['promo_price'] > 0 ? $product['price'] - $product['promo_price'] : $product['price'];
                    $product['price'] = $price;
                    unset($product['promo_price']);
                    $qty = $cartValue['qty'] * $product['qty_include'];

                    if ($qty > $product['limit']) {
                        return $this->sendError(
                            error: 'Maximum quantity',
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
                            error: 'Online Course not found',
                            errorMessages: ['type' => $cartValue['type'], 'content_id' => $cartValue['content_id']]
                        );
                    }

                    $product = $product->toArray();
                    $price = $product['promo_price'] > 0 ? $product['price'] - $product['promo_price'] : $product['price'];
                    $product['price'] = $price;
                    unset($product['promo_price']);

                    $transactionDetailData[] = [
                        'product' => $product,
                        'transaction_type' => $cartValue['type'],
                        'master_app_id' => $master_app->id,
                        'online' => $online,
                    ];

                } else if($cartValue['type'] == 'membership') {
                    // dd("Hello kids");
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
                    $price = $totalFromRequest;
                    // dd($price);
                    $product['item_name'] = $product['plan_name'];
                    $product['category_name'] = 'Membership';

                    $transactionDetailData[] = [
                        'product' => $product,
                        'transaction_type' => $cartValue['type'],
                        'master_app_id' => $master_app->id,
                        'online' => $online,
                    ];
                    // dd($transactionDetailData);
                }
                // dd($price, $totalPrice, "wwwww", $user['membership_plan_id']);
                $totalPrice += $price;
                // dd($totalPrice);
            }
            $totalFee = 0;
            $transactionFee = [];
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
                // dd($totalFee);
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
            // dd($isVoucher, $price);

            if($isVoucher && $price > 0) {
                $calculateDiscount = self::calculateDiscount($checkVoucher, $totalPrice);
                // dd("1");
                $amount = $calculateDiscount['amount'];
                $discount = $calculateDiscount['discount'];
            } else {
                // dd("2");
                $amount = $totalPrice;
                // dd($amount);
            }
            // dd("3");


            $amount = $amount < 0 ? 0 : $amount;
            // dd($amount, $totalFee);
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
                    'payment_method' => 'FREE',
                    'payment_progress' => 'success',
                ];
                // dd($transaction);

                $saveDb = self::saveToDB($transaction, $transactionDetailData, $transactionFee);
                self::getStatusPaymentXendit($transaction['payment_progress'], $order_id);
                if (!$saveDb) {
                    return $this->sendError(error: 'Tidak bisa menyimpan ke DB', code: 500);
                }

                CartV2::where('user_id', $user['id'])->delete();
                DB::commit();

                $result = [];
                $result['order_id'] = $order_id;
                $result['handphone'] = $handphone;
                $result['va_number'] = '';
                $result['payment_progress'] = 'success';
                $result['total_amount'] = $amount;
                return $this->sendResponse(result: $result, message: 'success');
            }else{
                $email = $request->get('email') ? $request->get('email') : $user['email'];
                $exp = 60 * 24;
                $now =Carbon::now();
                $next_date = date("Y-m-d\TH:i:s\Z", strtotime($now .' +1 day'));
                // dd($bank['fee']);
                $payload = [
                'currency' => 'IDR',
                'amount' => $totalFromRequest,
                // 'amount' => $amount + $bank['fee'],
                'payment_method' => array(
                'type' => 'VIRTUAL_ACCOUNT',
                'reusability' => 'ONE_TIME_USE',
                'reference_id' => 'pm-level-' . Str::uuid()->toString(),
                    'virtual_account' =>  array(
                        'channel_code' => $bank['code'],
                        'channel_properties' =>  array(
                                        'customer_name' => $user['first_name'],
                                        'expires_at'=> $next_date,
                        ),
                    ),
                ),
                    'metadata' =>  array(
                        'sku' => 'Payment Event Pinvest',
                    ),
                ];
                $payloads = json_encode($payload, JSON_UNESCAPED_SLASHES);
                // dd($payloads);
                // dd($order_id, $payloads, $this->ttlFiveDay);
                // $setInquiry = Helper::setRedis('inquiry_' . $order_id, $payloads, $this->ttlFiveDay);
                // if ($setInquiry) {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => $this->urlPaymentXendit .'payment_requests',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => array('Content-Type: application/json','Authorization: Basic '.$getKey),
                ));
                $response = curl_exec($curl);
                $results = json_decode($response);
                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if(!empty($results->failurs_code) && $results->failurs_code != '0000') {
                    return $this->sendError('Buat Transaksi Pembayaran Gagal', $results, code: 500);
                }
                // dd($totalFromRequest, $amount);
                $transaction = [
                    'user_id' => $user['id'],
                    'order_id' => $order_id,
                    // 'price' => $totalPrice,
                    'price' => $subTotal !== null 
                                ? $subTotal 
                                : ($amount >= $totalFromRequest 
                                    ? $totalFromRequest 
                                    : $amount),
                    'discount_type' => $isVoucher ? $checkVoucher['type'] : null,
                    // 'discount' => $isVoucher ? $discount : null,
                    'discount' => $isVoucher || $hargaPromo ? $diskonFromCart + $hargaPromo : null,
                    'voucher_number' => $isVoucher ? $voucher : null,
                    'va_number' => $results->payment_method->virtual_account->channel_properties->virtual_account_number ?? '',
                    'handphone' => $handphone,
                    'fee_pg' => $bank['fee'],
                    // 'total_amount' => $result->virtual_account->amount + $result['fee'],
                    // 'total_amount' =>$amount + $bank['fee'],
                    'total_amount' => $totalFromRequest >= $amount ? $totalFromRequest : $amount,
                    'status' => $results->status ?? '',
                    'payment_id' => $results->payment_method->id ?? '',
                    'payment_method' => $results->payment_method->type ?? '',
                ];
                // dd($transaction);
                // dd([
                //     "subTotal" =>$subTotal,
                //     "totalInCart" =>$totalInCart,
                //     "hargaPromo" =>$hargaPromo,
                //     "request" => $request,
                //     "transaction" => $transaction,
                //     "order_id" => $order_id,
                //     "result" => $results,
                //     "external_id" => $results->payment_method->id,
                // ]);
                // dd($transaction, $discount ,$hargaPromo, $diskonFromCart, $request);

                $saveDb = self::saveToDB($transaction, $transactionDetailData, $transactionFee);
                if (!$saveDb) {
                    return $this->sendError(error: 'Tidak bisa menyimpan ke DB', code: 500);
                }

                CartV2::where('user_id', $user['id'])->delete();

                DB::commit();
                $transaction['order_id'] = $order_id;
                $transaction['result'] = $results;
                $transaction['external_id'] = $results->payment_method->id;

                
                return $this->sendResponse(result: $transaction, message: 'success');

            }

        } catch (\Exception $e) {
            Log::error('error '.$e->getMessage());
            DB::rollback();
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }


    public function getStatusPaymentXendit($progress, $order_id)
    {
        DB::beginTransaction();
        $reconcile_id = strtoupper(Str::random(6) . "-" . Str::random(13));
        $date = date('Y-m-d H:i:s');

        $payment_progress = $progress;

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

        if($payment_progress == 'success') {
            $transactionDetail = TransactionDetail::select(['id', 'transaction_type', 'membership_duration_id','detail_event_id'])
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
                                    $qrCode = self::generateQrXendit();
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
                                $qrCode = self::generateQrXendit();
                                TransactionTicketQr::create([
                                    'transaction_detail_id' => $detailValue->id,
                                    'detail_event_id' => $event['detail_event_id'],
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
        $text = "0,Success,$reconcile_id,$order_id,$date";
        $response = Response::make($text, 200);
        $response->header('Content-Type', 'text/plain');
        return $response;

    }

    public function generateQrXendit()
    {
        $number = rand(1, 100000);
        $micro_date = microtime();
        $date_array = explode(" ", $micro_date);
        $microtime = str_replace(':', '', date('h:i:s')) . str_replace('.', '', $date_array[0]);
        return $microtime . $number;
    }

    public static function calculateDiscount($dataVoucher, $price)
    {
        $discountMax = $dataVoucher['max_discount'];
        $discountType = $dataVoucher['type'];
        $discount = $dataVoucher['discount'];
        // dd($dataVoucher, $price);
        $amount = 0;
        if ($discountType == 'nominal') {
            $newDiscount = $discount > $discountMax ? $discountMax : $discount;
            $amount = $price - $newDiscount;
            $discount = $newDiscount;

        } elseif ($discountType == 'percent') {
            $caculateDiscount = floor( ($discount / 100) * $price );
            $newDiscount = $caculateDiscount > $discountMax ? $discountMax : $caculateDiscount;
            $amount = $price - $newDiscount;
            $discount = $newDiscount;
        }
        // dd($amount);

        return [
            'amount' => $amount,
            'discount' => $discount,
        ];
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
            if (!$findVoucher) {
                return false;
            }

            return $findVoucher->toArray();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage());
            return false;
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

    public function detailTransactionV3(Request $request) {
        // dd($request);
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
                        $transaction['product']['date'] = [$detailEvent['date']];
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

            $transaction['total_fee'] = $transactionFee->sum('fee');
            $transaction['fee_detail'] = $transactionFee->toArray();
            $transaction['created_at'] = Helper::timestampToDateTime($transaction['created_at']);
            return $this->sendResponse(result: $transaction, message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Transaction Not Found', code: 404);

        } catch (\Exception $e) {
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
            $transaction->fee_pg = $result['fee_pg'] ?? 0;
            $transaction->total_amount = $result['total_amount'];
            $transaction->handphone = $result['handphone'];
            $transaction->discount = $result['discount'] ?? 0;
            $transaction->payment_method = $result['payment_method'] ?? null;
            $transaction->order_id = $result['order_id'];
            $transaction->va_number = $result['va_number'] ?? null;
            $transaction->discount_type = $result['discount_type'] ?? null;
            $transaction->voucher_number = $result['voucher_number'] ?? null;
            $transaction->external_id = $result['payment_id'] ?? null;
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
}

