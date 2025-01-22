<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionFee extends Model
{
    use HasFactory;

    protected $table = 'transaction_fee';
    public $timestamps = false;
    protected $guarded = ['id'];
}
