<?php

namespace App\Http\Controllers\API;


use Illuminate\Http\Request;
use App\Models\Inquiry;
use App\Models\Category;
use App\Models\Article;
//use App\Models\SubCategory;
//use Illuminate\Validation\Rules\In;
use File;
use App\Http\Controllers\API\UploadController;
use App\Library\Helper;
use App\Mail\InquiryMail;
use App\Models\InquiryAnswer;
use App\Models\InquiryQuestion;
use App\Models\MappingApp;
use App\Models\MappingFile;
use App\Models\MembershipApps;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class InquiryController extends BaseController
{
    protected $prefix;

    public function __construct()
    {
        $this->prefix = 'inquiry';
    }

    public function create(Request $request)
    {
        // dd($request);
        $rules = [
            'notes' => 'required',
            'app' => 'in:picapital,picircle,pispace',
            'file.*' => ['nullable', 'file', 'mimes:doc,docx,ppt,pptx,xls,xlsx,pdf,jpeg,jpg,png,gif,webp', 'max:5000'],
            'article_id' => 'required|integer',
        ];

        $user = $request->get('session_user');
        $user_id = $user['id'];
        $article_id = $request->get('article_id');
        $app = $request->get('app'); //? nama category

        $select = ['article.id', 'article.title', 'article.master_app_id'];
        $article = Article::select($select)
        ->join('master_app', 'article.master_app_id', '=', 'master_app.id')
        ->where('master_app.alias',$app)
        ->where('article.status','publish')
        ->where('article.id',$article_id)->first();

        // $article = Article::select($select)
        // ->join('master_app', 'article.master_app_id', '=', 'master_app.id')
        // ->where('master_app.alias',$app)
        // ->where('article.status','publish')->get();
        // dd($article);
        if(!$article){
            $errors='Tidak Ditemukan';
            return $this->sendError(code: 404,error:$errors);
        }

        $validator = Validator::make($request->all(),$rules);
        if($validator->fails()) {
            $messages=$validator->messages();
            $errors=$messages->all();
            return $this->sendError(code: 400,error:$errors);
        }

        $validateLimit = User::validateLimitInquiry($user, $article->master_app_id);
        if(!$validateLimit['valid']) {
            return $this->sendError($validateLimit['message'], code: 400);
        }

        try{
            $file = $request->file('file');
            $files = [];
            if (is_array($file)) {
                $uploadOss = new UploadController();
                foreach($file as $key => $value){
                    $uploadOssThumbnail = $uploadOss->ossUpload(file: $file[$key], prefix: "inquiry_$app");
                    $files[] = [
                        'name' => $uploadOssThumbnail['filename'],
                        'size' => $value->getSize(),
                        'url' => $uploadOssThumbnail['url'],
                    ];
                    unset($uploadOssThumbnail);
                }
            }

            $inquiry = new Inquiry();
            $inquiry->notes = strip_tags($request->get('notes'));
            $inquiry->created_by = $user_id;
            $inquiry->updated_by =$user_id;
            $inquiry->article_id = $article_id;
            $inquiry->master_app_id = $article->master_app_id;
            $inquiry->is_collabs = false;
            $inquiry->save();
            // dd($inquiry);

            $inquiryFiles = [];
            if(count($files) > 0) {
                foreach($files as $file) {
                    $inquiryFiles[] = ['file_name' => $file['name'], 'url_oss' => $file['url']];

                    // $data = [
                    //     'inquiry_id' => $inquiry->id,
                    //     'file_name' => $file['name'],
                    //     'file_size' => $file['size'],
                    //     'url_oss' => $file['url'],
                    // ];

                    MappingFile::create([
                        'inquiry_id' => $inquiry->id,
                        'file_name' => $file['name'],
                        'file_size' => $file['size'],
                        'url_oss' => $file['url'],
                    ]);
                }
            }

            $inquiryData = [
                'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'source' => $article['title'],
                'submission_date' => $inquiry->created_at,
                'notes' => $inquiry->notes,
                'app' => $app,
                'no_telp' => $user['no_hp'],
                'email' => $user['email'],
            ];

            $inquiryMail = new InquiryMail($inquiryData, $inquiryFiles);
            $data = Mail::to( env('MAIL_FROM_ADDRESS') )->send($inquiryMail);
            // dd($data);

            $result = ['files' => $files];
            return $this->sendResponse(result: $result, message: 'Permintaan Dibuat');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }
    public function listQuestions(Request $request)
    {
        $message = 'Daftar Pertanyaan Pertanyaan';
        $prefix = $this->prefix . '_questions';

        $checkRedis = Helper::getRedis($prefix);
        $getRedis = $checkRedis ?? false;
        if ($getRedis) {
            return $this->sendResponse(result: $getRedis, message: $message);
        }

        $allQuestions = InquiryQuestion::select(['id', 'question'])
            ->orderBy('order_number', 'ASC')->get();
        $allQuestions = $allQuestions->map( function($question) {
            $question->question = nl2br($question->question);
            $question->answers = $question->answers()
                ->select(['id', 'answer'])->get();

            return $question;
        });

        return $this->sendResponse(result: $allQuestions, message: $message);
    }
}
