<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\App;
use App\Models\Faq;
use App\Models\Gallery;
use Illuminate\Http\Request;

class FaqController extends BaseController
{
    protected $prefix;
    public function __construct()
    {
        $this->prefix = 'list_faq';

    }
    /**
     * @OA\Get(
     *     path="/api/faq/general",
     *     tags={"Faq"},
     *     @OA\Parameter(
     *          name="category",
     *          description="category",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Response(response="200", description="Display a listing of faq.")
     * )
     */
    public function general(Request $request)
    {
        $category = $request->get('category');
        $keys = array_keys(CATEGORY_FAQ);
        if(!in_array($category,$keys)){
            return $this->sendError('Kategori Tidak Ditemukan.');
        }
        
        $prefix = $this->prefix.'_general_'.$category;
        $message = 'Daftar Faq Umum '.$category;

        $select = ['id','title','content','status','category','order_number'];
        if($category == 'membership') {
            array_push($select, 'image');
        }

        $listFaq = Faq::select($select)
            ->where('status', 'publish')
            ->where('category', $category)
            ->orderBy('order_number', 'ASC');
        if($category=='faq'){
            $listFaq = $listFaq->get()->toArray();
        }else{
            $listFaq = $listFaq->first();
            if($listFaq){
                $listFaq = $listFaq->toArray();

                if($category == 'collabs-with-us') {
                    $listApp = App::select('id', 'app_name', 'alias', 'vector_image')
                        ->where('status', 'publish')->where('alias', '!=', 'homepage')
                        ->get()->toArray();
                    
                    $listFaq['apps'] = $listApp;
                }
            }
        }

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

        // Helper::setRedis($prefix, json_encode($listFaq), 500);
        return $this->sendResponse(result: $listFaq, message: $message);
    }
    /**
     * @OA\Get(
     *     path="/api/faq/{app}",
     *     tags={"Faq"},
     *     @OA\Parameter(
     *          name="app",
     *          description="app",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Response(response="200", description="Display a listing of faq.")
     * )
     */
    public function byApp(Request $request,$app)
    {
        $prefix = $this->prefix.'_'.$app;
        $message = "Daftar Faq $app";
        $listFaq = Faq::select(['faq.id','faq.title','faq.content','faq.status','master_app.alias'])
            ->join('master_app', 'faq.master_app_id','=', 'master_app.id')
            ->where('master_app.status', 'publish')
            ->where('faq.status', 'publish')
            ->where('master_app.alias', $app)
            ->get()->toArray();
        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        if ($getRedis) {
            return $this->sendResponse(result: $getRedis, message: $message);
        }
        Helper::setRedis($prefix, json_encode($listFaq), 500);
        return $this->sendResponse(result: $listFaq, message: $message);
    }
    public function gallery()
    {
        $prefix = $this->prefix.'_gallery';
        $message = "Daftar Galeri Tentang Kami";

        $allGallery = Gallery::select(['id', 'image'])
            ->where('status', 'publish')
            ->orderBy('order_number', 'asc')
            ->get()->toArray();

        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        if ($getRedis) {
            return $this->sendResponse(result: $getRedis, message: $message);
        }

        Helper::setRedis($prefix, json_encode($allGallery), 500);
        return $this->sendResponse(result: $allGallery, message: $message);
    }
}
