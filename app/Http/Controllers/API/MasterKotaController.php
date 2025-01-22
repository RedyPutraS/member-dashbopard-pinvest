<?php

namespace App\Http\Controllers\API;

use App\Library\Helper;
use App\Models\Kota;
use Illuminate\Http\Request;

class MasterKotaController extends BaseController
{
    public function index(Request $request)
    {
        $allKota = Kota::select(['id', 'kota'])
            ->orderBy('id', 'asc')->get()->toArray();

        return $this->sendResponse($allKota, 'List Kota');
    }

    // INSERT KE DATABASE DATA KOTA DI INDONESIA
    public function insertDataKota()
    {
        $json = file_get_contents('https://raw.githubusercontent.com/mtegarsantosa/json-nama-daerah-indonesia/master/regions.json');
        $json = json_decode($json, true);
        $json = array_column($json, 'kota');

        $dataKota = [];
        foreach($json as $value) {
            $dataKota = array_merge($dataKota, $value);
        }

        $dataKota = collect($dataKota)->sortBy( function($value) {
            return substr(trim(preg_replace('/kab\.|kota/i', '', $value)), 0, 1);
        });

        $dataKota = $dataKota->all();
        $dataKota = array_map( function($value) {
            return [
                'kota' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }, $dataKota);

        Kota::insert($dataKota);
    }
}