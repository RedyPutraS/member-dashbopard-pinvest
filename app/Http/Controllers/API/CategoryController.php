<?php

namespace App\Http\Controllers\API;

//use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends BaseController
{
    public function byAppId(int $id) : object
    {
        return $this->sendResponse(result: self::queryByAppId($id),message: 'Daftar Catgory');
    }
    public function queryByAppId (int  $id): array{
        $detail = Category::select('id','category_name','alias')->where('status','publish')
            ->where('master_app_id',$id)->orderBy('order_number', 'ASC')
            ->get()->toArray();
        return $detail;
    }
    public function queryByCategoryByApp($name): array{
        $detail = Category::select('master_category.id','master_category.category_name','master_category.alias')
            ->join('master_app','master_category.master_app_id','=','master_app.id')
            ->where('master_app.status','publish')
            ->where('master_category.status','publish')
            ->where('master_app.alias',$name)
            ->orderBy('order_number', 'ASC')
            ->get()->toArray();
        return $detail;
    }
}
