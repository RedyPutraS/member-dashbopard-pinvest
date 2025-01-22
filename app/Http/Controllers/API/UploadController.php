<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OSS\Core\OssException;
use OSS\OssClient;
use Illuminate\Support\Facades\Validator;


class UploadController extends Controller
{
    /*
        # ALIBABA CLOUD
    */
    // public function upload(Request $request)
    // {
    //     if ($request->hasFile('file')) {
    //         $image = $request->file('file');
    //         $typeFile = $request->get('type_file') ;
    //         if($typeFile==='image'){
    //             $validator = Validator::make($request->all(), [
    //                 'upload' => 'required|image',
    //             ]);
    //             if ($validator->fails()) {
    //                 return response()->json([
    //                     'fileName' => $image->getClientOriginalName(),
    //                     'uploaded'=> 0,
    //                     'location' => '',
    //                 ],400);
    //             }

    //         }

    //         $uploadOss = self::ossUpload(file:$image,prefix: 'upload_content_');
    //         return response()->json([
    //             'fileName' => $uploadOss['filename'],
    //             'uploaded'=> 1,
    //             'location' => $uploadOss['url'],
    //         ]);
    //     }
    // }

    // public function ossUpload($file,$prefix=null,$filename=null)
    // {
    //     $rand = rand(1,10000);
    //     $createFilename = $filename ?? $prefix.date('YmdHis').$rand;
    //     $updateFileName = self::extractFile($file, $createFilename);
    //     $filePut = Storage::putFileAs('local',$file, $updateFileName);
    //     $filePath = storage_path('app')."/$filePut";
    //     $extension = $file->extension();

    //     // if($extension == 'jpg' || $extension == 'jpeg') {
    //     //     $imageWidth = getimagesize($filePath)[0];
    //     //     $imageCopy = imagecreatefromjpeg($filePath);
    //     //     $imageResized = imagescale($imageCopy, $imageWidth*99/100);
    //     //     imagejpeg($imageResized, $filePath);
    //     // }elseif($extension == 'png'){
    //     //     $imageWidth = getimagesize($filePath)[0];
    //     //     $imageCopy = imagecreatefrompng($filePath);
    //     //     $imageResized = imagescale($imageCopy, $imageWidth*99/100);
    //     //     imagepng($imageResized, $filePath);
    //     // }

    //     $accessKeyId = env('OSS_ACCESS_KEY_ID');
    //     $accessKeySecret = env('OSS_SECRET_ACCESS_KEY');
    //     $endpoint = env('OSS_ENDPOINT');
    //     $bucket = env('OSS_BUCKET');
    //     $object = env('OSS_BUCKET_PATH').$updateFileName;
    //     try {
    //         $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
    //         $detail = $ossClient->uploadFile($bucket, $object, $filePath);
    //         if(!isset($detail['info']['url'])){
    //             return false;
    //         }
    //         $data = [
    //             'filename' => $createFilename,
    //             'url'=> $detail['info']['url'],
    //         ];
    //         return $data;

    //     } catch (OssException $e) {
    //         return false;
    //     }
    // }


    /*
        # AMAZON AWS CLOUD S3
    */
    public function upload(Request $request)
    {
        $image = $request->file('file');
        $rand = rand(1,10000);
        $imageFileName = 'upload_content_' .  date('YmdHis').$rand . '.' . $image->getClientOriginalExtension();
        $imageFileUrl = Storage::disk('s3')->putFileAs('Uploads', $image, $imageFileName);
        $imageFileUrl = Storage::disk('s3')->url($imageFileUrl);

        return response()->json([
            'fileName' => $imageFileName,
            'uploaded'=> 1,
            'location' => $imageFileUrl,
        ]);
    }


    public function ossUpload($file,$prefix=null,$filename=null)
    {
        // dd($file);
        $rand = rand(1,10000);
        $createFilename = $filename ?? ($prefix.date('YmdHis').$rand . '.' . $file->getClientOriginalExtension());
        $imageFileUrl = Storage::disk('s3')->putFileAs('Uploads', $file, $createFilename);
        if(!$imageFileUrl) return false;

        $imageFileUrl = Storage::disk('s3')->url($imageFileUrl);
        // dd($imageFileUrl);
        return [
            'filename' => $createFilename,
            'url'=> $imageFileUrl,
        ];
    }

    public function extractFile($filename, $aliases = null) : string
    {
        if (!$filename):
            throw new Error('nama file kesalahan kosong');
        endif;


        $extension = $filename->getClientOriginalExtension();
        $name = pathinfo($filename->getClientOriginalName(), PATHINFO_FILENAME);


        $name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
        $aliases = preg_replace('/[^A-Za-z0-9\-]/', '', $aliases);

        $name = str_replace(' ', '_', strtolower($name) . '_' . Carbon::now()->format('U') . '.' . $extension);

        $aliases = str_replace(' ', '_', strtolower($aliases) . '_' . Carbon::now()->format('U') . '.' . $extension);


        $filename = $aliases;
        if (empty($aliases)):
            $filename = $name;
        endif;

        return $filename;
    }
}
