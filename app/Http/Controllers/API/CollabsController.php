<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Mail\InquiryMail;
use App\Models\App;
use App\Models\Faq;
use App\Models\Inquiry;
use App\Models\InquiryAnswer;
use App\Models\InquiryFile;
use App\Models\InquiryQuestion;
use App\Models\InquiryQuestionAnswer;
use App\Models\MappingApp;
use App\Models\MappingFile;
use App\Models\MasterBenefit;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class CollabsController extends BaseController
{
    protected $category = 'collabs-with-us';
    protected $excludeApp = ['homepage', 'collabs'];

    public function index(Request $request)
    {
        try {
            $listFaq = Faq::select(['id','title','content','status','category','order_number'])
                ->where('status', 'publish')
                ->where('category', $this->category)
                ->orderBy('order_number', 'ASC')
                ->firstOrFail()->toArray();

            $listApp = App::select('id', 'app_name', 'alias', 'vector_image', 'description')
                ->where('status', 'publish')->whereNotIn('alias', $this->excludeApp)->orderBy('order_number','ASC')
                ->get()->toArray();
            
            $listFaq['apps'] = $listApp;
            return $this->sendResponse(result: $listFaq, message: 'Dapatkan Kolaborasi');

        } catch (ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function syarat(Request $request)
    {
        try {
            $listFaq = Faq::select(['id','title','content','status'])
                ->where('status', 'publish')
                ->where('category', 'syarat-ketentuan')
                ->firstOrFail()->toArray();
            return $this->sendResponse(result: $listFaq, message: 'Dapatkan Syarat');

        } catch (ModelNotFoundException $e){
            return $this->sendError(error: 'Tidak Ditemukan', code: 404);

        } catch (\Exception $e) {
            $err = env('APP_DEBUG', false) ? ', ' . $e->getMessage() : '';
            return $this->sendError(error: 'Kesalahan Server Internal' . $err, code: 500);
        }
    }

    public function detail($app)
    {
        $detailApp = App::select('id', 'app_name', 'alias', 'banner_image', 'description')
            ->where('status', 'publish')->where('alias', $app)
            ->first()->toArray();
        
        $partnershipBenefit = MasterBenefit::select('id', 'title', 'image', 'description')
            ->where('type', 'partnership')->get()->toArray();

        $result = ['detail' => $detailApp, 'partnership_benefit' => $partnershipBenefit];
        return $this->sendResponse(result: $result, message: 'Dapatkan Kolaborasi ' . $app);
    }

    public function inquiryQuestions()
    {
        $message = 'Daftar Pertanyaan Pertanyaan';
        // $prefix = $this->prefix . '_questions';
        
        // $checkRedis = Helper::getRedis($prefix);
        // $getRedis = $checkRedis ?? false;
        // if ($getRedis) {
        //     return $this->sendResponse(result: $getRedis, message: $message);
        // }

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

    public function createInquiry(Request $request)
    {
        $listApp = App::select('alias')->where('status', 'publish')
            ->whereNotIn('alias', $this->excludeApp)->get()
            ->pluck('alias')->toArray();

        $rules = [
            'app' => 'required|in:' . implode(',', $listApp),
            'notes' => 'required',
            'file.*' => ['nullable', 'file', 'mimes:doc,docx,ppt,pptx,xls,xlsx,pdf,jpeg,jpg,png,gif,webp', 'max:5000'],
        ];

        $user = $request->get('session_user');
        $user_id = $user['id'];
        $app = $request->get('app');
        $answers = $request->get('answers');
        $arrayAnswers = json_decode($answers, true);
        $is_collabs = true;

        if(empty($answers) || !is_array($arrayAnswers) || count($arrayAnswers) == 0) {
            return $this->sendError(code: 400, error: 'Kolom jawaban wajib diisi');
        }

        $dataApp = App::select('id', 'app_name', 'alias', 'banner_image', 'description')
            ->where('status', 'publish')->where('alias', $app)->first();
        if(!$dataApp) {
            return $this->sendError(code: 400, error: 'Aplikasi data tidak valid');
        }

        $validator = Validator::make($request->all(),$rules);
        if($validator->fails()) {
            $messages=$validator->messages();
            $errors=$messages->all();
            return $this->sendError(code: 400,error:$errors);
        }

        $validateLimit = User::validateLimitInquiry($user, $dataApp->id);
        if(!$validateLimit['valid']) {
            return $this->sendError($validateLimit['message'], code: 400);
        }

        try{ 
            $file = $request->file('file');
            $files = [];
            if (is_array($file)) {
                $uploadOss = new UploadController();
                foreach($file as $key => $value){
                    $uploadOssThumbnail = $uploadOss->ossUpload(file: $file[$key], prefix: "inquiry_collabs");
                    $files[] = [
                        'name' => $uploadOssThumbnail['filename'],
                        'size' => $value->getSize(),
                        'url' => $uploadOssThumbnail['url'],
                    ];

                    unset($uploadOssThumbnail);
                }
            }

            $inquiry = new Inquiry();
            $inquiry->master_app_id = $dataApp->id;
            $inquiry->notes = strip_tags($request->get('notes'));
            $inquiry->created_by = $user_id;
            $inquiry->updated_by = $user_id;
            $inquiry->is_collabs = $is_collabs;
            $inquiry->save();

            $inquiryFiles = [];
            if(count($files) > 0) {
                foreach($files as $file) {
                    $inquiryFiles[] = ['file_name' => $file['name'], 'url_oss' => $file['url']];

                    MappingFile::create([
                        'inquiry_id' => $inquiry->id,
                        'file_name' => $file['name'],
                        'file_size' => $file['size'],
                        'url_oss' => $file['url'],
                    ]);
                }
            }

            $questionAnswer = [];
            foreach($arrayAnswers as $value) {
                $idQuestion = $value['id_question'] ?? '';
                $idAnswer = $value['id_answer'] ?? '';
                if(empty($idQuestion) || empty($idAnswer)) continue;

                $questionAnswer[] = [
                    'question' => InquiryQuestion::find($idQuestion, ['question'])->question ?? '',
                    'answer' => InquiryQuestionAnswer::find($idAnswer, ['answer'])->answer ?? '',
                ];

                InquiryAnswer::create([
                    'inquiry_id' => $inquiry->id,
                    'inquiry_question_id' => $idQuestion,
                    'inquiry_question_answer_id' => $idAnswer,
                ]);
            }

            $inquiryData = [
                'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'source' => 'Collabs With Us',
                'submission_date' => $inquiry->created_at,
                'notes' => $inquiry->notes,
            ];

            $inquiryMail = new InquiryMail($inquiryData, $inquiryFiles, $questionAnswer);
            Mail::to( env('MAIL_FROM_ADDRESS') )->send($inquiryMail);

            $result = [
                'files' => $files,
                'question_answered' => count($questionAnswer)
            ];
            return $this->sendResponse(result: $result, message: 'Penyelidikan Dibuat');

        } catch (\Exception $e) {
            return $this->sendError(error: 'Kesalahan Server Internal, ' . $e->getMessage(), code: 500);
        }
    }
}