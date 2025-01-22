<?php

namespace App\Http\Controllers\API;

use App\Models\App;

use Illuminate\Http\Request;
use Helper;
use Auth;

class ApplicationController extends BaseController
{
    protected $prefix;
    protected $exceptApp = ['homepage', 'collabs'];

    public function __construct()
    {
        $this->prefix = 'list_apps';
    }
    /**
     * @OA\Get(
     *     path="/application",
     *     tags={"Application"},
     *     @OA\Response(response="200", description="Display a listing of application.")
     * )
     */
    public function indexx(Request $request)
{
    $prefix = $this->prefix;
    $message = 'Daftar aplikasi';

    // Mengambil daftar aplikasi yang dipublikasikan dan tidak dalam daftar pengecualian
    $listApp = App::select('id', 'app_name', 'alias', 'vector_image')
        ->where('status', 'publish')
        ->whereNotIn('alias', $this->exceptApp)
        ->orderBy('order_number', 'ASC')
        ->get()
        ->toArray();

    $with = $request->input('with');
    if ($with === 'category') {
        $prefix .= '_with_category';
        $message .= 'dengan kategori';
    } elseif ($with === 'subcategory') {
        $prefix .= '_with_subcategory';
        $message .= 'dengan subkategori';
    }

    if ($with === 'category' || $with === 'subcategory') {
        $newCategory = new CategoryController();
        $newSubCategory = new SubCategoryController();
        
        // Mengambil kategori untuk setiap aplikasi
        foreach ($listApp as $key => $value) {
            $listApp[$key]['category'] = $newCategory->queryByAppId($value['id']);
        }

        if ($with === 'subcategory') {
            foreach ($listApp as $appKey => $item) {
                if ($item["alias"] == "pispace" || $item["alias"] == "picapital" || $item["alias"] == "picast") {
                    // Jika aplikasi adalah pispace atau picapital, skip logika filtering
                    continue;
                } else {
                    // Filter kategori berdasarkan subkategori
                    $item['category'] = array_filter($item['category'], function($data) use ($appKey, $request, $listApp) {
                        $requestSubcat = new Request([
                            'limit' => 1,
                            'start' => 0,
                            'filter' => 'new',
                            'search' => '',
                            'category' => $data['alias'],
                        ]);

                        // Memanggil ArticleController
                        $controllerArticle = new ArticleController();
                        $responseArticle = $controllerArticle->index($requestSubcat, $listApp[$appKey]['alias']);
                        $dataArticle = $responseArticle->getData();
                        $arrayDataArticle = json_decode(json_encode($dataArticle), true);
                        $dataaArticle = $arrayDataArticle['data'];

                        // Memanggil OnlineCourseController jika aliasnya onlinecourse
                        if ($data['alias'] === "onlinecourse") {
                            $controllerOnline = new OnlineCourseController();
                            $responseOnline = $controllerOnline->index($requestSubcat);
                            $dataOnline = $responseOnline->getData();
                            $arrayDataOnline = json_decode(json_encode($dataOnline), true);
                            $dataaOnline = $arrayDataOnline['data'];
                        }

                        // Memanggil EventController jika dataaArticle kosong
                        if (empty($dataaArticle) && $data['alias'] !== "onlinecourse") {
                            $controllerEvent = new EventController();
                            $responseEvent = $controllerEvent->index($requestSubcat, $listApp[$appKey]['alias']);
                            $dataEvent = $responseEvent->getData();
                            $arrayDataEvent = json_decode(json_encode($dataEvent), true);
                            $dataaEvent = $arrayDataEvent['data'];
                        }

                        if (empty($dataaArticle) && $data['alias'] !== "onlinecourse" && $data['alias'] !== "fullstackprogram") {
                            if ($data['alias'] == "webinar") {
                                $controllerEvent = new EventController();
                                $responseEvent = $controllerEvent->index($requestSubcat, $listApp[$appKey]['alias']);
                                // dd($responseEvent["data"], "asdghasjhgd");
                                $dataEvent = $responseEvent->getData();
                                if ($dataEvent->length == 0) {
                                    $dataEvent = json_decode(json_encode($responseEvent), true);
                                    dd($dataaEvent);
                                }
                                $arrayDataEvent = json_decode(json_encode($dataEvent), true);
                                $dataaEvent = $arrayDataEvent['data'];
                            }
                            
                        }

                        // Cek apakah ada data
                        return !empty($dataaArticle) || !empty($dataaOnline) || !empty($dataaEvent);
                    });

                    // Jika kategori menjadi kosong, hapus dari listApp
                    if (empty($item['category'])) {
                        unset($listApp[$appKey]);
                    } else {
                        $listApp[$appKey]['category'] = array_values($item['category']); // Reset array index
                    }
                }
            }

            // Tambahkan logika untuk menangani aplikasi "pispace"
            foreach ($listApp as $appKey => $item) {
                if ($item["alias"] === "pispace") {
                    // Jika kategori kosong, tetap simpan item pispace
                    if (empty($item['category'])) {
                        $listApp[$appKey]['category'] = []; // Atau data default jika perlu
                    }
                }
            }
            foreach ($listApp as $appKey => $item) {
                if ($item["alias"] === "picast") {
                    // Jika kategori kosong, tetap simpan item pispace
                    if (empty($item['category'])) {
                        $listApp[$appKey]['category'] = []; // Atau data default jika perlu
                    }
                }
            }
        }
    }

    return $this->sendResponse(result: $listApp, message: $message);
}




public function index(Request $request)
    {
        $prefix = $this->prefix;
        $message = 'List apps';

        $listApp = App::select('id', 'app_name', 'alias', 'vector_image')
            ->where('status', 'publish')->whereNotIn('alias', $this->exceptApp)
            ->orderBy('order_number', 'ASC')->get()->toArray();

        $with = $request->input('with');
        if ($with === 'category') {
            $prefix .= '_with_category';
            $message .= 'with category';
        } elseif ($with === 'subcategory') {
            $prefix .= '_with_subcategory';
            $message .= 'with subcategory';
        }

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }


        if ($with === 'category' || $with === 'subcategory') {
            $newCategory = new CategoryController();
            $newSubCategory = new SubCategoryController();
            foreach ($listApp as $key => $value) {
                $listApp[$key]['category'] = $newCategory->queryByAppId($value['id']);
            }
            
            if ($with === 'subcategory') {
                foreach ($listApp as $value => $item) {
                    foreach ($item['category'] as $key => $data) {
                        $listApp[$value]['category'][$key]['subcategory'] = $newSubCategory->queryByCategoryId($data['id']);
                    }
                }
            }
        }

        // Helper::setRedis($prefix, json_encode($listApp), 500);
        return $this->sendResponse(result: $listApp, message: $message);
    }
}
