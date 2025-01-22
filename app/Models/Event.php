<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected $table = 'event';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
