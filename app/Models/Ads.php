<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ads extends Model
{
    use HasFactory;

    protected $table = 'ads';
    protected $guarded = ['id'];

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

    public function apps()
    {
        return $this->hasMany(MappingApp::class, 'ads_id', 'id'); 
    }

    public function items()
    {
        return $this->hasMany(AdsItem::class, 'ads_id', 'id');
    }
}
