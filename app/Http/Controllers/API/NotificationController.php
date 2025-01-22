<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends BaseController
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $sort = $request->get('sort', 'desc');
        $start = $request->get('start', 0);
        $limit = $request->get('limit', 10);
        $limit = $limit > 100 ? 100 : $limit;

        $user = $request->get('session_user');
        $select = [
            'notification.id', 
            'notification.transaction_id', 
            'transaction.order_id',  
            'transaction.payment_progress',  
            'notification.title', 
            'notification.content', 
            'notification.is_read', 
            'notification.created_at'
        ];

        $allNotif = Notification::select($select)
            ->join('transaction', 'transaction.id', 'notification.transaction_id')
            ->where('notification.user_id', $user['id'])
            ->orderBy('notification.created_at', $sort)
            ->limit($limit)->offset($start);

        if($filter == 'unread') {
            $allNotif->where('is_read', false);
        } else if($filter == 'read') {
            $allNotif->where('is_read', true);
        }

        $allNotif = $allNotif->get()->toArray();
        return $this->sendResponse($allNotif, 'Pemberitahuan Daftar.');
    }

    public function read(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'notification_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError(error: 'Permintaan yang buruk', errorMessages: $validator->errors(), code: 400);
        }

        $notification = Notification::find($request->notification_id);
        $notification->is_read = true;
        $notification->save();

        return $this->sendResponse([], 'Pemberitahuan Berhasil Dibaca.');
    }
}
