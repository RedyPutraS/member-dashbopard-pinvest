<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingApp extends Model
{
    use HasFactory;

    protected $table = 'mapping_app';
    protected $guarded = ['id'];
    public $timestamps = false;
}
