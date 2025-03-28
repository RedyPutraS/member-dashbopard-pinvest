<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingFile extends Model
{
    use HasFactory;

    protected $table = 'mapping_file';
    protected $guarded = ['id'];
    public $timestamps = false;
}
