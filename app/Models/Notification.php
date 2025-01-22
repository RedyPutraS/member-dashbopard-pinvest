<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notification';
    protected $guarded = ['id_notification'];
    public $timestamps = true;

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

    public static function getTransactionItemName($dataDetail)
    {
        $text = '';
        foreach($dataDetail as $key => $value) {
            if(isset($value['ticket_pass_id'])) continue;
            
            $spacer = $key == 0 ? '' : (($key + 1) == count($dataDetail) ? ', dan ' : ', ');
            $text .= $spacer . $value['item_name'];
        }

        return $text;
    }
}
