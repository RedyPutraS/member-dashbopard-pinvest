<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InquiryFile extends Model
{
    use HasFactory;

    protected $table = 'inquiry_file';
    protected $guarded = ['id'];
    public $timestamps = false;
}
