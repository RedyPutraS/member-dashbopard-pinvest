<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InquiryAnswer extends Model
{
    use HasFactory;

    protected $table = 'inquiry_answer';
    protected $guarded = ['id'];
}
