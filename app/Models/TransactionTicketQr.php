<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionTicketQr extends Model
{
    use HasFactory;

    protected $table = 'transaction_ticket_qr';
    protected $guarded = ['id'];
    public $timestamps = false;
}
