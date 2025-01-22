<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailEvent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'detail_event';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function event()
    {
        return $this->hasOne(Event::class, 'id', 'event_id');
    }
}
