<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnlineCourse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'online_course';
    const CREATED_AT = 'created_at'; 
    const UPDATED_AT = 'updated_at';

    public static function checkPurchased($id, $userId) {
        return TransactionDetail::join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
            ->where('transaction_detail.online_course_id', $id)
            ->where('transaction.user_id', $userId)
            ->where('transaction.payment_progress', 'success')->count() > 0;
    }
}
