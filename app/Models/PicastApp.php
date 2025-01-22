<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PicastApp extends Model
{
    protected $fillable = ['youtube_id','spotify_id','title'];
    protected $table = 'picast_app';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
