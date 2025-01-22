<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipExclusive extends Model
{
    use HasFactory;
    
    protected $table = 'membership_exclusive';
    public $timestamps = false;
}
