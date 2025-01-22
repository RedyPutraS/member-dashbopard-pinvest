<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $casts = [
        'rate' => 'float',
    ];
    
    use HasFactory;
    protected $fillable = ['notes','title','rate','user_id','youtube_id','spotify_id'];
    protected $table = 'rating';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected function createdAt() : Attribute
    {
        return Attribute::make(
            get: fn($value) => date('Y-m-d H:i:s', strtotime($value))
        );
    }
    
    protected function updatedAt() : Attribute
    {
        return Attribute::make(
            get: fn($value) => date('Y-m-d H:i:s', strtotime($value))
        );
    }
}
