<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Space_package extends Model
{
    use HasFactory;

    protected $table = 'space_package';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
