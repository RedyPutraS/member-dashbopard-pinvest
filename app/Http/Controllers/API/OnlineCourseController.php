<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\MappingInstructor;
use Illuminate\Http\Request;
use App\Models\OnlineCourse;
use App\Models\Benefit;
use App\Models\DetailOnlineCourse;
use App\Models\MappingFile;
use App\Models\ModulOnlineCourse;
use App\Models\Rating;
use App\Models\SectionOnlineCourse;
use App\Models\TransactionDetail;
use App\Models\WishlistV2;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OnlineCourseController extends BaseController
{
    protected $prefix;
    public function __construct()
    {
        $this->prefix = 'online_course';
    }

    private function checkAddedToWishlist(Request $request, $id)
    {
        $user = $request->get('session_user');
        if(!$user) return false;

        return WishlistV2::where('type', 'online-course')
            ->where('user_id', $user['id'])->where('content_id', $id)->count() > 0;
    }

    /**
     * @OA\Get(
     *     path="/api/pilearning/online_course",
     *     tags={"PiLearning"},
     *     @OA\Response(response="200", description="Display a listing of Online Course.")
     * )
     */
    public function index(Request $request, $rawResponse = false)
    {
        $message = 'Daftar Kursus Online ';
        // $prefix = 'list_'.$this->prefix;
        $search = $request->input('search');
        $filter = $request->input('filter');
        $sort = $request->input('sort');
        $limit = $request->input('limit') ?? 12;
        $limit = $limit>100 ? 100 : (int) $limit;
        $user = $request->get('session_user');

        // if($search){
        //     $prefix.= '_'.$search;
        // }

        // if($filter){
        //     $prefix.= '_'.$filter;
        // }

        // $prefix .= '_' . $start . '_' . $limit;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        $select = [
            'online_course.id',
            'online_course.image as cover_image',
            'online_course.thumbnail_image',
            'online_course.title',
            'online_course.type',
            'online_course.meta_title',
            'online_course.meta_description',
            'online_course.meta_keyword',
            'online_course.status',
            DB::raw('CONCAT(users.first_name, \' \', users.last_name) AS author'),
            'online_course.voucher',
            'online_course.price',
            'online_course.promo_price',
            DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'),
            DB::raw('COUNT(rating.id) AS rating_count'),
            'online_course.description',
            'online_course.video_length AS duration',
            'online_course.created_at',
        ];

        $online_course = OnlineCourse::select($select)
            ->leftjoin('users', 'online_course.created_by','=', 'users.id')
            ->leftjoin('rating', 'online_course.id','=', 'rating.online_course_id')
            ->where('online_course.status','=','publish');

        if($filter) {
            $arrFilter = json_decode(rawurldecode($filter), true);
            
            if(isset($arrFilter['type']) && !is_null($arrFilter['type'])) {
                $online_course = $online_course->where('type', $arrFilter['type']);
            }
            if(!empty($arrFilter['rating'])) {
                $rate = $arrFilter['rating'];
                $online_course = $online_course->having(DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0)'), '>=', $rate);
            }
        }

        if($sort) { 
            if($sort=='new') {
                $online_course = $online_course->orderBy('online_course.id','DESC');
            } elseif ($sort=='title-asc') {
                $online_course = $online_course->orderBy('online_course.title','ASC');
            } elseif ($sort=='title-desc') {
                $online_course = $online_course->orderBy('online_course.title','DESC');
            } elseif($sort=='popular') {
                $online_course = $online_course->orderBy('online_course.id','ASC');
            }
        }

        if($search){
            $online_course = $online_course->where(DB::raw('LOWER(online_course.title)'), 'LIKE', '%'.strtolower(trim($search)).'%')
                ->orWhere(DB::raw('LOWER(online_course.meta_description)'), 'LIKE', '%' . strtolower(trim($search)) . '%');
        }

        $groupBy = [
            'online_course.id',
            'users.first_name',
            'users.last_name'
        ];

        $online_course = $online_course->groupBy($groupBy);
        $online_course = $online_course->limit($limit)->paginate($limit)->toArray();

        foreach ($online_course['data'] as $key => $value){
            $selectInstructor = [ 'instructor.id','instructor.name','instructor.title','instructor.description','instructor.image'];
            $instructor = MappingInstructor::select($selectInstructor) 
                ->join('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id')
                ->where('mapping_instructor.online_course_id', $value['id'])->first()->toArray();

            if(!$rawResponse) unset($online_course['data'][$key]['created_at']);
            
            $online_course['data'][$key]['instructor'] = $instructor;
            $online_course['data'][$key]['rate'] = round($online_course['data'][$key]['rate'], 1);
            $online_course['data'][$key]['description'] = Helper::shortDescription($online_course['data'][$key]['description']);
            $online_course['data'][$key]['duration'] = Helper::formatDuration( Helper::getDuration($online_course['data'][$key]['duration']) );
            $online_course['data'][$key]['added_to_wishlist'] = $this->checkAddedToWishlist($request, $online_course['data'][$key]['id']);
            if($online_course['data'][$key]['type'] == 'free') {
                $online_course['data'][$key]['purchased'] = true;
            } else if(!$user) {
                $online_course['data'][$key]['purchased'] = false;
            } else {
                $online_course['data'][$key]['purchased'] = OnlineCourse::checkPurchased($online_course['data'][$key]['id'], $user['id']);
            }
        } 
        
        if($rawResponse) return $online_course['data'];
        $pagination = Helper::getPaginationData($online_course, $limit);

        // Helper::setRedis($prefix,json_encode($online_course),500);
        return $this->sendResponse(result: $online_course['data'], message: $message, pagination: $pagination);
    }

    /**
     * @OA\Get(
     *     path="/api/pilearning/online_course/{id}",
     *     tags={"PiLearning"},
     *     @OA\Parameter(
     *          name="id",
     *          description="Online Course id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          )
     *      ),
     *     @OA\Response(response="200", description="Display a listing of Online Course.")
     * )
     */
    public function detail($id, Request $request)
    {
        try {
            $user = $request->get('session_user');
            $message = "Daftar Kursus Online $id";
            $prefix = 'detail_'.$this->prefix.'-'.$id;
            /*$checkRedis = Helper::getRedis($prefix);
            $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
            if ($getRedis) {
                return $this->sendResponse(result: $getRedis, message: $message);
            }*/

            ReferralController::store($request,'online-course',$id);
            $select = [
                'online_course.id',
                'online_course.image as thumbnail_image',
                'online_course.thumbnail_video',
                'online_course.title',
                'online_course.type',
                'online_course.meta_title',
                'online_course.meta_description',
                'online_course.meta_keyword',
                'online_course.status',
                DB::raw('CONCAT(users.first_name, \' \', users.last_name) AS author'),
                'online_course.voucher',
                'online_course.price',
                'online_course.promo_price',
                'online_course.updated_at',
                'online_course.language',
                'online_course.requirement',
                'online_course.description',
                'online_course.benefit',
                DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'),
                DB::raw('COUNT(rating.rate) AS rating_count'),
                DB::raw('COUNT(distinct rating.user_id) AS rating_user_count'),
                'online_course.video_length AS duration',
            ];
            $online_course = OnlineCourse::leftjoin('rating', 'online_course.id','=', 'rating.online_course_id')
                ->leftjoin('users', 'online_course.created_by','=', 'users.id')
                ->select($select)
                ->where('online_course.status','=','publish')
                ->where('online_course.id','=',$id);

            $groupBy = [
                'online_course.id',
                'users.first_name',
                'users.last_name'
            ];
            $online_course = $online_course->groupBy($groupBy);
            $online_course = $online_course->firstOrFail()->toArray();

            $selectInstructor = [ 'instructor.id','instructor.name','instructor.title','instructor.description','instructor.image'];
            $instructor = MappingInstructor::select($selectInstructor)
                        ->join('instructor', 'mapping_instructor.instructor_id','=', 'instructor.id')
                        ->where('mapping_instructor.online_course_id', $online_course['id'])->get()->toArray();
            $online_course['instructor'] = $instructor;

            $online_course['rate'] = round($online_course['rate'], 1);
            $online_course['benefit'] = json_decode($online_course['benefit'], true);
            $online_course['description'] = json_decode($online_course['description'], true);
            $online_course['duration'] = Helper::formatDuration( Helper::getDuration($online_course['duration']) );
            $online_course['added_to_wishlist'] = $this->checkAddedToWishlist($request, $online_course['id']);
            $online_course['article_count'] = count($online_course['description']);
            $online_course['file_count'] = MappingFile::where('online_course_id', $online_course['id'])->count();

            if($online_course['type'] == 'free') {
                $online_course['purchased'] = true;
            } else if(!$user) {
                $online_course['purchased'] = false;
            } else {
                $online_course['purchased'] = OnlineCourse::checkPurchased($online_course['id'], $user['id']);
            }

            //Helper::setRedis($prefix,json_encode($online_course),500);
            return $this->sendResponse(result: $online_course,message: $message);

        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Kursus Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function play($id, Request $request)
    {
        try {
            $user = $request->get('session_user');
            // dd($user);

            $select = [
                'online_course.id',
                'online_course.thumbnail_image',
                'online_course.image as cover_image',
                'online_course.thumbnail_video',
                'online_course.title',
                'online_course.meta_title',
                'online_course.type',
                'online_course.description_course',
                'online_course.video_url',
                'online_course.video_length',
                'online_course.video_length AS duration',
            ];
            $online_course = OnlineCourse::select($select)
                ->where('online_course.status','=','publish')
                ->where('online_course.id','=',$id)
                ->firstOrFail()->toArray();
            // dd($online_course);
            // dd($online_course['type'] == 'premium');

            if($online_course['type'] == 'premium') {
                // validasi kalo premium
                $checkCourse = OnlineCourse::checkPurchased($id, $user['id']);
                if(!$checkCourse) {
                    return $this->sendError(error: 'Anda tidak memiliki akses ke kursus ini.', code: 403);
                }
            }

            $online_course['description'] = json_decode($online_course['description_course'], true);
            unset($online_course['description_course']);
            $online_course['duration'] = Helper::formatDuration( Helper::getDuration($online_course['duration']) );

            $sectionCourse = SectionOnlineCourse::where('online_course_id', $id)
                ->orderBy('from_duration', 'asc')->get()->toArray();
            $online_course['section'] = $sectionCourse;

            $fileCourse = MappingFile::select('id', 'file_name', 'file_size', 'url_oss')
                ->where('online_course_id', $id)->orderBy('id', 'asc')->get()->toArray();
            $online_course['files'] = $fileCourse;

            return $this->sendResponse($online_course, message: "Mainkan Kursus Online $id");
        } catch(ModelNotFoundException $e){
            return $this->sendError(error: 'Kursus Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }
     
    public function detailWithRating($id)
    {
        $prefix = $this->prefix.'_detail_online_course_rating_'.'_'.$id;
        // $checkRedis = Helper::getRedis($prefix);
//        $getRedis = $checkRedis ? json_decode($checkRedis) : false;
//        if($getRedis){
//            return $getRedis;
//        }

        $select = [
            'online_course.image',
            'online_course.title',
            'online_course.video_length AS duration',
            'online_course.description',
        ];
        $onlineCourse = OnlineCourse::select($select)->where('online_course.id','=',$id)->first();
        if(!$onlineCourse) return null;

        $getRating = Rating::select([
            DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'),
            DB::raw('COUNT(rating.id) AS rating_count'),
        ])->where('online_course_id', $id)->first();
        
        $onlineCourse->rate = round($getRating->rate, 1);
        $onlineCourse->rating_count = $getRating->rating_count;
        $onlineCourse = $onlineCourse->toArray();

        $onlineCourse['description'] = Helper::shortDescription($onlineCourse['description']);
        $onlineCourse['duration'] = Helper::formatDuration( Helper::getDuration($onlineCourse['duration']) );
        return $onlineCourse;
    }

}
