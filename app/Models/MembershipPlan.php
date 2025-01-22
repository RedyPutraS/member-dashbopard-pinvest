<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'membership_plan';

    public function apps()
    {
        return $this->hasMany(MappingApp::class, 'membership_plan_id', 'id')
            ->select(['master_app_id', 'master_app.app_name', 'master_app.alias'])
            ->join('master_app', 'master_app.id', 'mapping_app.master_app_id');
    }
    
    public function durations()
    {
        return $this->hasMany(MembershipDuration::class, 'membership_plan_id', 'id')
            ->select(['id', 'name', 'type', 'duration', 'price', 'status'])
            ->where('status', 'publish');
    }

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
