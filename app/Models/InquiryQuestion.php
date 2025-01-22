<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InquiryQuestion extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'inquiry_question';

    public function answers()
    {
        return $this->hasMany(InquiryQuestionAnswer::class, 'question_id', 'id');
    }
}
