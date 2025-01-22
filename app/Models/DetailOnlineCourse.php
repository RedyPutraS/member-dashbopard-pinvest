<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailOnlineCourse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'detail_online_course';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
