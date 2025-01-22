<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdsItem extends Model
{
    use HasFactory;

    protected $table = 'ads_item';
    protected $guarded = ['id'];
    public $timestamps = false;
}
