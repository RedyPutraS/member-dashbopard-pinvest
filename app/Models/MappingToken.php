<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MappingToken extends Model
{
    use HasFactory;

    protected $table = 'mapping_token';
    protected $guarded = ['id'];
    public $timestamps = false;
}
