<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\DetailEvent;
use Illuminate\Http\Request;
use App\Models\MappingInstructor;

class InstructorController extends BaseController
{
    protected $prefix;
    
    public function __construct()
    {
        $this->prefix = 'list_instructor_event';

    }
    public function byDetailEventId(Request $request,int $id)
    {
        $with = $request->get('with');
        $detail = DetailEvent::findOrFail($id)->toArray();
        $message = 'Daftar Instruktur Berdasarkan ID Acara '. $id;
        $prefix = $this->prefix.'_'.$id;
        if($with=='detail'){
            $prefix = $this->prefix.'_detail_'.$id;
        }
        
        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ? json_decode($checkRedis) : false;
        if($getRedis) {
            return $this->sendResponse(result: $getRedis,message: $message);
        }

        $select = ['instructor.name'];
        if($with=='detail'){
            array_push($select,'instructor.title','instructor.description','instructor.username_instructor',);
        }

        if($detail['ticket_pass']){
            $listEvent = json_decode($detail['ticket_pass_id'],true);
            $listInstructor = MappingInstructor::select($select)
                ->join('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id');
            $listInstructor = $listInstructor->whereIn('mapping_instructor.detail_event_id',$listEvent)->get()->toArray();
        }else{
            $listInstructor = MappingInstructor::select($select)
                ->join('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id');
            $listInstructor = $listInstructor->where('mapping_instructor.detail_event_id',$id)->get()->toArray();

        }

        Helper::setRedis($prefix,json_encode($listInstructor),500);
        return $this->sendResponse(result: $listInstructor,message: $message);
    }
}
