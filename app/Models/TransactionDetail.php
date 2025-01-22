<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class TransactionDetail extends Model
{
    use HasFactory;

    protected $table = 'transaction_detail';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
