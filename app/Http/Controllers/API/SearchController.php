<?php

namespace App\Http\Controllers\API; 

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\BaseController;
use App\Models\Category;
use Illuminate\Http\Request;

class SearchController extends BaseController
{
    private function putResultType($result, $type)
    {
        return array_map( function($value) use($type) {
            $value['result_type'] = $type;
            return $value;
        }, $result);
    }

    private function mergeResult(...$result)
    {
        $finalResult = [];
        $includedResult = [];

        foreach($result as $values) {
            foreach($values as $value) {
                $resultType = $value['result_type'];
                $searchResultType = $includedResult[$resultType] ?? [];
                if( in_array($value['id'], $searchResultType) ) continue;

                $finalResult[] = $value;
                $includedResult[$resultType][] = $value['id'];
            }
        }

        return $finalResult;
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $start = $request->get('start', 0);
        $limit = $request->get('limit', 30);

        $request->request->remove('start');
        $request->request->remove('limit');

        // article | event | onlinecourse
        $articleController = new ArticleController();
        $resultArticlePilearning = $this->putResultType( $articleController->index($request, 'pilearning', true), 'article');
        $resultArticlePicircle = $this->putResultType( $articleController->index($request, 'picircle', true), 'article');
        $resultArticlePinews = $this->putResultType( $articleController->index($request, 'pinews', true), 'article');

        $eventController = new EventController();
        $resultEventPilearning = $this->putResultType( $eventController->index($request, 'pilearning', true), 'event');
        $resultEventPievent = $this->putResultType( $eventController->index($request, 'pievent', true), 'event');

        $onlineCourseController = new OnlineCourseController();
        $resultOnlineCourse = $this->putResultType( $onlineCourseController->index($request, true), 'online-course');

        $allResult = $this->mergeResult($resultArticlePilearning, $resultArticlePicircle, $resultArticlePinews, $resultEventPilearning, $resultEventPievent, $resultOnlineCourse);
        $allResult = collect($allResult);

        $allResult = $allResult->sortByDesc( function($result) {
            return strtotime( $result['result_type'] == 'article' ? $result['publish_at'] : $result['created_at'] );
        });

        $allResult = $allResult->map( function($value) {
            if(isset($value['created_at'])) unset($value['created_at']);
            return $value;
        });

        $allResult = $allResult->splice($start, $limit);
        return $this->sendResponse($allResult, 'Search Result');
    }

    public static function keywordIsApp($keyword)
    {
        $app = ['pilearning', 'picircle', 'pispace', 'pinews', 'pievent', 'picast'];
        $keyword = trim(strtolower($keyword));
        return in_array($keyword, $app);
    }
}