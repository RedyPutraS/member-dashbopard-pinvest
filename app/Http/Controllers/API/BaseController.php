<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\Controller;
    
class BaseController extends Controller
{
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($result, $message, $pagination = null, $status = 'success')
    {
    	$response = [
            'status' =>  $status,
            'message' => $message,
            'data'    => $result,
        ];

        if(is_array($response)) {
            $response['page'] = $pagination;
        }

        return response()->json($response, 200);
    }

    public function userDetail($result, $message)
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data'    => $result,

        ];


        return response()->json($response, 200);
    }


    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
    	$response = [
            'status' => 'failed',
            'message' => $error,
        ];


        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }


        return response()->json($response, $code);
    }
}
