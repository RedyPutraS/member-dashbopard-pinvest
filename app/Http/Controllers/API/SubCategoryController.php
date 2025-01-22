<?php

namespace App\Http\Controllers\API;

//use Illuminate\Http\Request;
use App\Models\SubCategory;

class SubCategoryController extends BaseController
{
    public function byCategoryId(int $id) : object
    {
        return $this->sendResponse(result: self::queryByCategoryId($id),message: 'Daftar SubKategori');
    }
    public function queryByCategoryId (int  $id): array{
        $detail = SubCategory::select('id','subcategory_name','alias')->where('status','publish')->where('master_category_id',$id)->get()->toArray();
        return $detail;
    }

    public function queryByCategoryId2 (int  $id): array{
        $detail = SubCategory::select('id','subcategory_name','alias')->where('status','publish')->where('master_category_id',$id)->get()->toArray();
        return $detail;
    }

}
