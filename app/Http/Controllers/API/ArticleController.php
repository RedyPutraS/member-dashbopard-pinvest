<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\Article;
use App\Models\Space_package as SpacePackage;
use App\Http\Controllers\API\ReferralController;
use App\Library\Helper;
use App\Models\App;
use App\Models\Category;
use App\Models\Comment;
use App\Models\LikeShare;
use App\Models\Rating;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArticleController extends BaseController
{
    protected $prefix;

    public function __construct()
    {
        $this->prefix = 'article';
    }
    /**
     * @OA\Get(
     *     path="/api/pilearning/article",
     *     tags={"PiLearning"},
     *     @OA\Response(response="200", description="Display a listing of article.")
     * )
     */
    public function index(Request $request, $app, $rawResponse = false)
    {
        // dd($request->input('subcategory'));
        $message = 'Daftar Artikel ' . $app;
        $prefix = 'list_' . $app . '_' . $this->prefix;
        $limit = $request->input('limit', 12);
        $filter = $request->input('filter');
        // dd($filter);
        $limit = $limit > 100 ? 100 : (int)$limit;

        $search = $request->input('search');
        $sort = $request->get('sort') ?? $request->get('filter');
        // dd($sort);
        $category = $request->input('category') ?? 'article';
        if ($app !== 'pilearning') {
            $category = $request->input('category');
        }
        $subcategory = $request->input('subcategory');
        // dd($category, $subcategory);

        if ($search) {
            $prefix .= '_' . $search;
        }
        if ($sort) {
            $prefix .= '_' . $sort;
        }
        if ($category) {
            $prefix .= '_' . $category;
        }
        if ($subcategory) {
            $prefix .= '_' . $subcategory;
        }

        $prefix .= '_' . $limit;
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        // if ($getRedis) {
            //     return $this->sendResponse(result: $getRedis, message: $message);
            // }
        // dd($subcategory);

        $checkApp = App::where('alias', $app)->count();
        if($checkApp == 0) {
            return $this->sendError('Application Alias is invalid');
        }

        $select = [
            'article.id',
            'article.thumbnail_image',
            'article.title',
            'article.meta_description as description',
            DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS author"),
            'article.publish_at',
            'article.view',
        ];
        if($app == 'picircle') {
            array_push($select, 'article.view', 'article.view');
        }

        if ($app !== 'pispace') {
            array_push($select, 'master_category.category_name', 'master_subcategory.subcategory_name');
        }

        if ($category == 'directory') {
            $directory = [
                'article.province',
                'article.city',
                'article.address',
                'article.google_location',
                'article.place',
            ];
            $select = array_merge($select, $directory);
        }
        // dd($select);

        $article = Article::select($select)->join('master_app', 'article.master_app_id', '=', 'master_app.id')
        ->join('users', 'article.author', '=', 'users.id');
        if ($app !== 'pispace') {
            $article = $article->leftjoin('master_subcategory', 'article.master_subcategory_id', '=', 'master_subcategory.id')
            ->leftjoin('master_category', 'article.master_category_id', '=', 'master_category.id');
        }
        $article = $article->where('master_app.alias', '=', $app)
        ->where('article.status', '=', 'publish');

        if ($sort == 'title-asc') {
            $article = $article->orderBy(DB::raw('LOWER(SUBSTRING(article.title, 1, 1))'), 'ASC');
        } elseif ($sort == 'title-desc') {
            $article = $article->orderBy(DB::raw('LOWER(SUBSTRING(article.title, 1, 1))'), 'DESC');
        } elseif ($sort == 'popular') {
            $article = $article->orderBy('article.view', 'DESC');
        } elseif ($sort == 'asc') {
            $article = $article->orderBy('article.created_at', 'ASC');
        } else {
            $article = $article->orderBy('article.created_at', 'DESC');
        }

        if ($category) {
            $article = $article->where('master_category.alias', '=', $category);
        }
        // dd( $category, $subcategory, "muehehehe");
        if ($subcategory) {
            if ($category != "article" && $category != "directory" && $category != "forum" && $category != null) {
                // Jika ada `category`, langsung cek berdasarkan `master_category.alias`
                // dd($subcategory, $category);
                $article = $article->where('master_category.alias', '=', $subcategory);
            } else if($category == "directory" || $category == "forum") {
                // Clone query builder untuk pengecekan tanpa mempengaruhi query utama
                // $cekQuery = clone $article;

                
                // // Cek jika data ada di `master_subcategory.alias`
                // $hasSubcategory = $cekQuery->where('master_subcategory.alias', '=', $subcategory)->exists();
        
                // if ($hasSubcategory) {
                    // Jika ada data di `master_subcategory.alias`, gunakan filter ini
                    $article = $article->where('master_subcategory.alias', '=', $subcategory);
                // } else {
                    // Jika tidak ada data di `master_subcategory.alias`, cek di `master_category.alias`
                    $article = $article->where('master_category.alias', '=', $category);
                    // dd($article->get());
                // }
            } else {
                // Clone query builder untuk pengecekan tanpa mempengaruhi query utama
                $cekQuery = clone $article;

                
                // Cek jika data ada di `master_subcategory.alias`
                $hasSubcategory = $cekQuery->where('master_subcategory.alias', '=', $subcategory)->exists();
        
                if ($hasSubcategory) {
                    // Jika ada data di `master_subcategory.alias`, gunakan filter ini
                    $article = $article->where('master_subcategory.alias', '=', $subcategory);
                } else {
                    // Jika tidak ada data di `master_subcategory.alias`, cek di `master_category.alias`
                    $article = $article->where('master_category.alias', '=', $subcategory);
                }
            }
        }
        // dd("hello om");
        // dd($article->get());
        if ($search) {
            $keywordIsApp = SearchController::keywordIsApp($search);
            if(!$keywordIsApp || $app != $search) {
                $article = $article->where(DB::raw('LOWER(article.title)'), 'LIKE', '%' . strtolower(trim($search)) . '%')
                ->orWhere(DB::raw('LOWER(article.meta_description)'), 'LIKE', '%' . strtolower(trim($search)) . '%')
                ->orWhere(DB::raw('LOWER(master_category.category_name)'), 'LIKE', '%' . strtolower(trim($search)) . '%')
                ->orWhere(DB::raw('LOWER(article.content)'), 'LIKE', '%' . strtolower(trim($search)) . '%');

                if($app !== 'pispace') {
                    $article = $article->orWhere(DB::raw('LOWER(master_subcategory.subcategory_name)'), 'LIKE', '%' . strtolower(trim($search)) . '%');
                }
            }
        }
        // dd($category);
        // dd($category == null);
        if ($app == 'pispace') {
            $article = $article->paginate($limit)->toArray();
            // dd($article);
        } else {
            // dd($article->get(), $app, $category, $subcategory);
            
            if ($subcategory == "inspiration") {
                $article = $article->where('master_subcategory.alias', $subcategory);
                $article = $article->paginate($limit)->toArray();
            }else if($category != "pinspire" || $category == null){
                // dd("bukan pin");
                // dd("hello");
                $article = $article->where('master_category.alias', '!=', 'pinspire');
                // $article = $article->where('master_app.app_name', '!=', 'PiNspire');
                // dd($article);
                $article = $article->paginate($limit)->toArray();
                // $article['data'] = array_map( function($value) use($app) {
                //     if($app == 'picircle') {
                //         $value['like'] = LikeShare::where('article_id', $value['id'])->count();
                //         $value['comment'] = Comment::where('article_id', $value['id'])->count();
                //     }

                //     $rating = Rating::select(DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'))
                //     ->where('article_id', $value['id'])->first()->toArray();

                //     $value['rate'] = round($rating['rate'], 1);
                //     return $value;
                // }, $article['data']);
                // dd($article);
            } else if ($category == "pinspire") {
                // dd("pin");
                // dd("hello om");
                $article = $article->where('master_category.alias', 'pinspire');
                $article = $article->paginate($limit)->toArray();
                // dd($article);
            }
        }
        $article['data'] = array_map( function($value) use($app) {
            if($app == 'picircle') {
		        $value['view'] = Article::select('view')->where('id', $value['id'])->first()->view;
                $value['like'] = LikeShare::where('article_id', $value['id'])->count();
                $value['comment'] = Comment::where('article_id', $value['id'])->count();
            }

            $rating = Rating::select(DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'))
            ->where('article_id', $value['id'])->first()->toArray();

            $value['rate'] = round($rating['rate'], 1);
            return $value;
        }, $article['data']);

        // $article = $article->paginate($limit)->toArray();
        // $article['data'] = array_map( function($value) use($app) {
        //     if($app == 'picircle') {
        //         $value['like'] = LikeShare::where('article_id', $value['id'])->count();
        //         $value['comment'] = Comment::where('article_id', $value['id'])->count();
        //     }

        //     $rating = Rating::select(DB::raw('coalesce(CAST(AVG(rating.rate) AS FLOAT),0) AS rate'))
        //     ->where('article_id', $value['id'])->first()->toArray();

        //     $value['rate'] = round($rating['rate'], 1);
        //     return $value;
        // }, $article['data']);
        // dd($article);

        // $dataArticle = [];
        // if ($category != "PiNSpire") {
        //     for ($i=0; $i <= count($article["data"]); $i++) {
        //         if (!empty($article["data"][$i]["category_name"])) {
        //             if ($article["data"][$i]["category_name"] == "PiNSpire") {
        //                 unset($article["data"][$i]);
        //             } else {
        //                 array_push($dataArticle ,$article["data"][$i]);
        //                 // unset($article["data"][$i]);
        //             }
        //         } else {
        //             // dd($article["data"]);
        //             array_push($dataArticle ,$article["data"]);
        //             // unset($article["data"][$i]);
        //         }
        //     }
        //     // dd($category."157");
        // }
        // // dd($dataArticle);
        // // dd($article["data"]);
        // // dd(count($article["data"]));
        // // foreach ($article["data"] as $data=> $value) {
        //     //     # code...
        //     // }
        //     // dd($article["data"]);
        //     // dd($dataArticle);
        //     // unset($article["data"], $article["data"]);
        //     $article["data"];
        //     // dd($article["data"]);
        //     // dd($dataArticle);
        //     for ($i=0; $i < count($dataArticle); $i++) {
        //         array_push($article["data"], $dataArticle[$i]);
        //     }
            // dd($article["data"]);
            // dd($article);
            // dd($dataArticle[0]);
        // array_push($article["data"], $dataArticle);
        // dd($article);

        if($rawResponse) return $article['data'];
        $pagination = Helper::getPaginationData($article, $limit);

        // Helper::setRedis($prefix, json_encode($article), 500);
        return $this->sendResponse(result: $article['data'], message: $message, pagination: $pagination);
    }

    public function detail(Request $request,$app, int $id)
    {
        $dataDetail = Article::findOrFail($id);
        $message = "Detail Article $id";
        $prefix = 'detail_' . $app . '_' . $this->prefix . '-' . $id;

        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ? json_decode($checkRedis, true) : false;
        ReferralController::store($request,'article',$id);
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }


        $select = [
            'article.id',
            'article.meta_title',
            'article.meta_description',
            'article.meta_keyword',
            'article.thumbnail_image',
            'article.cover_image',
            'article.title',
            'article.content as description',
            'article.content',
            'users.first_name AS author',
            'article.publish_at',
            'article.province',
            'article.city',
            'article.address',
            'article.google_location',
            'article.place',
            'article.view'
        ];
        
        // Hanya tambahkan kolom master_category jika diperlukan
        if ($app !== 'pispace') {
            array_push($select, 'master_category.alias', 'master_category.category_name', 'master_subcategory.subcategory_name');
        }
        
        $article = Article::select($select)
            ->join('master_app', 'article.master_app_id', '=', 'master_app.id')
            ->join('users', 'article.author', '=', 'users.id');
        
        if ($app !== 'pispace') {
            $article = $article
                ->leftJoin('master_subcategory', 'article.master_subcategory_id', '=', 'master_subcategory.id')
                ->leftJoin('master_category', 'article.master_category_id', '=', 'master_category.id'); // leftJoin untuk opsional
        }
        
        $article->where('master_app.alias', '=', $app)
            ->where('article.status', '=', 'publish')
            ->where('article.id', '=', $id);
        
        // Mengambil hasil pertama
        $article = $article->first();
        // dd($article);
        if(!$article){
            return $this->sendError(error:'Tidak Ditemukan');
        }
            
            $article= $article->toArray();
            if ($app == 'pispace') {
                $package = SpacePackage::select(['id', 'name', 'content', 'price', 'status'])
                ->where('status', 'publish')
                ->where('article_id', $id)
                ->get()->toArray();
                $article['packages'] = $package;
            }
            
            //* Add View +1
            $dataDetail->view += 1;
            $dataDetail->save();

        // Helper::setRedis($prefix, json_encode($article), 500);
        return $this->sendResponse(result: $article, message: $message);
    }

    public function create(Request $request)
    {
        $categoryAlias = 'forum'; // hardcode
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'meta_description' => 'required|max:255',
            'meta_keyword' => 'required|max:255',
            'thumbnail_image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:7000',
            'cover_image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:7000',
            'description' => 'required',
            'content' => 'required',
            'subcategory' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->sendError(error: 'Bad request', errorMessages: $validator->errors(), code: 400);
        }

        $user = $request->get('session_user');
        $category = Category::select('id', 'master_app_id')->where('alias', $categoryAlias)->first();
        if(!$category) {
            return $this->sendError('Kategori tidak ditemukan.');
        }

        $subcategory = SubCategory::select('id')->where('alias', $request->subcategory)->first();
        if(!$subcategory) {
            return $this->sendError('Kategori tidak ditemukan.');
        }
        if($subcategory->id == 36){
            $master_subcategori = new SubCategory;
            $master_subcategori->master_category_id = 18;
            $master_subcategori->subcategory_name = $request->kategori_lainnya;
            $master_subcategori->created_by = Auth::user()->id;
            $master_subcategori->updated_by = Auth::user()->id;
            $master_subcategori->status = 'publish';
            $master_subcategori->alias = 'kategorilainnya';
            $master_subcategori->save();
        }

        $thumbnailImage = $request->file('thumbnail_image');
        $uploadOss = new UploadController();
        $uploadOssThumbnail = $uploadOss->ossUpload(file: $thumbnailImage, prefix: "article_thumbnail");

        $coverImage = $request->file('cover_image');
        $uploadOssCover = $uploadOss->ossUpload(file: $coverImage, prefix: "article_cover");

        $input = $request->only([ 'title', 'meta_description', 'meta_keyword', 'description', 'content', 'category', 'subcategory' ]);
        $input['thumbnail_image'] = $uploadOssThumbnail['url'];
        $input['cover_image'] = $uploadOssCover['url'];
        $input['master_app_id'] = $category->master_app_id;
        $input['master_category_id'] = $category->id;
        $input['master_subcategory_id'] = $subcategory->id;
        if($categoryAlias === 'forum'){
            $input['status'] = 'publish';
        }else{
            $input['status'] = 'unpublish';
        }
        $input['author'] = $user['id'];
        $input['created_by'] = $user['id'];
        $input['updated_by'] = $user['id'];

        Article::create($input);
        return $this->sendResponse($input, 'Pembuatan Artikel berhasil, mohon tunggu artikel Anda segera ditinjau oleh tim kami.');
    }
}
