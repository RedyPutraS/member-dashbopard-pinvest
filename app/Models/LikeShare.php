<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LikeShare extends Model
{
    use HasFactory;

    protected $table = 'like_share';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
