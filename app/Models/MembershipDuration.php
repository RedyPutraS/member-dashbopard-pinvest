<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipDuration extends Model
{
    use HasFactory;
    
    protected $table = 'membership_duration';
    public $timestamps = false;

    // lang = ENG / ID
    public static function getDurationNameFormat($durationType, $duration, $lang) {
        $engArrays = [
            'daily' => 'Day', 
            'monthly' => 'Month', 
            'yearly' => 'Year', 
        ];

        $idArrays = [
            'daily' => 'Hari', 
            'monthly' => 'Bulan', 
            'yearly' => 'Tahun', 
        ];
        
        $durationType = strtr($durationType, $lang == 'ENG' ? $engArrays : $idArrays);
        return $duration . ' ' . $durationType . ($lang == 'ENG' && $duration > 1 ? 's' : '');
    }
}
