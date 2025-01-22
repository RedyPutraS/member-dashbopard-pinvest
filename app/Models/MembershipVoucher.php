<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipVoucher extends Model
{
    use HasFactory;
    
    protected $table = 'membership_voucher';
    public $timestamps = false;
    protected $guarded = ['id'];
}
