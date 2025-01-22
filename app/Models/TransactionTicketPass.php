<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionTicketPass extends Model
{
    use HasFactory;

    protected $table = 'transaction_ticket_pass';
    protected $guarded = ['id'];
    public $timestamps = false;
}
