<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Request;

class WishlistV2 extends Model
{
    use HasFactory;

    protected $table = 'wishlist_v2';
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

    public static function checkAddedToWishlist(Request $request, $type, $id)
    {
        $user = $request->get('session_user');
        if(!$user) return false;

        return WishlistV2::where('user_id', $user['id'])->where('type', $type)
            ->where('content_id', $id)->count() > 0;
    }
}
