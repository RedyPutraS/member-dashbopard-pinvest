<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['payment_progress','va_number'];
    protected $table = 'transaction';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function detail()
    {
        return $this->hasOne(TransactionDetail::class, 'transaction_id', 'id');
    }
}
