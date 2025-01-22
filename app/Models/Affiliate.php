<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Affiliate extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $casts = [
        'created_at' =>'date:Y-m-d H:i:s',
    ];

    protected $table = 'affiliate';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
