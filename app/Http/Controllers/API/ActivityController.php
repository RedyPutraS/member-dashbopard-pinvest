<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\DetailEvent;
use App\Models\MembershipDuration;
use App\Models\TransactionDetail;
use App\Models\TransactionTicketQr;
use Illuminate\Http\Request;

class ActivityController extends BaseController
{
    private function getTabs()
    {
        return [
            [
                'tab_name' => 'Acara',
                'alias' => 'event'
            ],
            [
                'tab_name' => 'Kursus Online',
                'alias' => 'online-course'
            ],
            [
                'tab_name' => 'Keanggotaan',
                'alias' => 'membership'
            ]
        ];
    }

    private function getActiveTab($tabs, $alias)
    {
        return array_map( function($value) use($alias) {
            $value['active'] = strtolower($value['alias']) == strtolower($alias);
            return $value;
        }, $tabs);
    }

    public function index(Request $request)
    {
        $allTabs = $this->getTabs();
        $activeTab = $request->get('tab', $allTabs[0]['alias']);
        $allTabs = $this->getActiveTab($allTabs, $activeTab);
        $user = $request->get('session_user');
        $start = (int) $request->get('start', 0);
        $limit = (int) $request->get('start', 10);

        $selectDetail = [
            'transaction.id',
            'transaction.order_id',
            'transaction_detail.id AS transaction_detail_id',
            'transaction_detail.detail_event_id',
            'transaction_detail.online_course_id',
            'transaction_detail.membership_duration_id',
            'transaction_detail.qty',
            'transaction_detail.online',
            'transaction_detail.ticket_pass',
            'transaction_detail.price',
        ];

        $transactionDetail = TransactionDetail::select($selectDetail)
            ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
            ->where('transaction.user_id', $user['id'])
            ->where('transaction_detail.transaction_type', $activeTab)
            ->where('transaction.payment_progress', 'success')
            ->orderBy('transaction.created_at', 'DESC')->limit($limit)->offset($start)
            ->get()->toarray();
        // dd($transactionDetail, "transactionDetail");

        $transactionDetail = array_map(function($value) use($activeTab) {
            if($activeTab == 'online-course') {
                $detailOnlineCourse = (new OnlineCourseController())->detailWithRating($value['online_course_id']);
                // dd($detailOnlineCourse, "detailOnlineCourse");
                $value['product'] = $detailOnlineCourse;

            } else if($activeTab == 'event') {
                // dd($value);
                $detailEvent = (new EventController())->detailWithRating($value['detail_event_id']);
                // dd($detailEvent, "detailEvent");
                $value['product'] = $detailEvent;

                if($value['ticket_pass']){
                    $date = [];
                    $count_seconds = 0;
        
                    $dateTransactionDetail = DetailEvent::select('date','start_time','end_time')
                        ->join('transaction_ticket_pass', 'transaction_ticket_pass.detail_event_id', 'detail_event.id')
                        ->where('transaction_ticket_pass.transaction_id', $value['transaction_detail_id'])
                        ->orderBy('date', 'ASC')->get()->toarray();
                        // dd($dateTransactionDetail);

                    foreach ($dateTransactionDetail as $to => $items){
                        $extract_date=$items['date'].' '.$items['start_time'];
                        $extract_end_date=$items['date'].' '.$items['end_time'];
                        $from_time = strtotime($extract_date);
                        $to_time = strtotime($extract_end_date);
                        $count_seconds += abs($to_time - $from_time);
                        $date[] = $items['date'];
                    }
        
                    $duration = Helper::formatDuration( Helper::getDuration($count_seconds) );
                    unset($date);
                    unset($dateTransactionDetail);
        
                } else {
                    $extract_date = ($detailEvent['date'] ?? '') . ' ' . ($detailEvent['start_time'] ?? '');
                    $extract_end_date = ($detailEvent['date'] ?? '') . ' ' . ($detailEvent['end_time'] ?? '');

                    // dd($extract_end_date, '$extract_end_date');
                    $from_time = strtotime($extract_date);
                    $to_time = strtotime($extract_end_date);
        
                    $duration = Helper::formatDuration(Helper::getDuration( abs($to_time - $from_time) ));
                    // dd($duration);
                }

                $value['product']['duration'] = $duration;
                $qrCode = [];
                
                $transactionQrCode = TransactionTicketQr::select(['qr_code'])
                    ->where('transaction_detail_id', $value['id'])->get()->toArray();
                foreach($transactionQrCode as $valueQr) {
                    $qrCode[] = $valueQr;
                }
                // dd(!isset($value['product']['url_meeting']));
                if (!isset($value['product']['url_meeting'])) {
                    $value['url_meeting'] = "-";
                } else {
                    $value['url_meeting'] = $value['product']['url_meeting'];
                    unset($value['product']['url_meeting']);
                }

                $value['qr_code'] = $qrCode;
            
            } else if($activeTab == 'membership') {
                $selectProduct = [
                    'membership_plan.id AS membership_plan_id',
                    'membership_plan.plan_name',
                    'membership_plan.thumbnail_image',
                    'membership_duration.name AS duration_name',
                    'membership_duration.type AS duration_type',
                    'membership_duration.duration',
                ];
        
                $product = TransactionDetail::select($selectProduct)
                    ->join('membership_duration', 'transaction_detail.membership_duration_id', '=', 'membership_duration.id')
                    ->join('membership_plan', 'membership_duration.membership_plan_id', '=', 'membership_plan.id')
                    ->where('transaction_detail.id', $value['transaction_detail_id'])->first();
                if(!$product) {
                    $value['product'] = [];

                } else {
                    $product = $product->toArray();
                    $product['duration'] = strval($product['duration']);
                    $product['title'] = MembershipDuration::getDurationNameFormat($product['duration_type'], $product['duration'], 'ENG');
                    $value['product'] = $product;
                }
            }
	    $value['price'] = number_format($value['price'],2, '.', '');
            return $value;
        }, $transactionDetail);

        $result = ['tab' => $allTabs, 'result' => $transactionDetail];
        return $this->sendResponse($result, 'Daftarkan Aktivitas Saya');
    }
}