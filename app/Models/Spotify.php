<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spotify extends Model
{
    use HasFactory;

    protected $table = 'spotify';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
