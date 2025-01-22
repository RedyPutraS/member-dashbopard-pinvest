<?php

namespace App\Library;

use Exception;
use GuzzleHttp\Client;
use DateTime;
use App\Providers\AppServiceProvider;
use Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use JasonGrimes\Paginator;


class Helper
{
    public $config;
    function __construct()
    {
        self::$config = '';
    }

    public static function getRedis($prefix)
    {
        // dd($prefix);
        try {
            $cek = Redis::get($prefix);
            dd($cek);
            return Redis::get($prefix);
        } catch (Exception $e) {
            dd($e);
            return false;
        }
    }

    public static function csrf(){
        $prefix = 'csrf_token:';
        $expiredTime = 3600;
        $token = base64_encode(openssl_random_pseudo_bytes(300).date('Y-m-d H:i:s'));
        self::setRedis($prefix.$token, $token, $expiredTime);
        $output = [
            'csrf_token' => $token,
            'expired_time' => $expiredTime

        ];

        return $output;
    }

    public static function verify_csrf($csrfToken){
        $prefix = 'csrf_token:'.$csrfToken;
        $getToken = self::getRedis($prefix);
        if($getToken){
            self::delRedis($prefix);
        }

        return $getToken;
    }

    public static function setRedis($prefix, $value,$ttl=null)
    {
        try {
            
            $setRedis = Redis::set($prefix,$value);
            dd($prefix,$value);

            if($setRedis && $ttl){
                Redis::expire($prefix, $ttl);
            }
            return Redis::set($prefix,$value,'EX',$ttl);
        } catch (Exception $e) {
            return $e;
        }
    }
    public static function incrRedis($prefix, $value)
    {
        try {
            $setRedis = Redis::incrby($prefix, $value);
            return self::getRedis($prefix);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function decrRedis($prefix, $value)
    {
        try {
            Redis::decrby($prefix, $value);
            return self::getRedis($prefix);

        } catch (Exception $e) {
            return false;
        }


    }
    public static function delRedis($prefix)
    {
        try {
            return Redis::del($prefix);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function getTime($thisTime)
    {
        try {
            $timeNow = date('Y-m-d H:i:s');
            $startTime = new \DateTime($thisTime);
            $endTime = new \DateTime($timeNow);
            $diff = $startTime->diff($endTime);
            $second = $diff->s;
            $hour = $diff->h;
            $minute = $diff->i;
            $day = $diff->d;
            $month = $diff->m;
            $year = $diff->y;
            if ($year > 0) {
                $time = $year . ' tahun yang lalu';
            } elseif ($month > 0) {
                $time = $month . ' bulan yang lalu';
            } elseif ($day > 0) {
                $time = $day . ' hari yang lalu';
            } elseif ($hour > 0) {
                $time = $hour . ' jam yang lalu';
            } elseif ($minute > 0) {
                $time = $minute . ' menit yang lalu';
            } elseif ($second > 0) {
                $time = $second . ' detik yang lalu';
            }
            
            return $time;
        } catch (Exception $e) {
            return false;
        }
    }
    public static function now(){
        return Carbon::now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
    }
    public static function arrayColumnExt($array, $columnkey, $indexkey = null) {
        $result = array();
        foreach ($array as $subarray => $value) {
            if (array_key_exists($columnkey,$value)) { $val = $array[$subarray][$columnkey]; }
            else if ($columnkey === null) { $val = $value; }
            else { continue; }
                
            if ($indexkey === null) { $result[] = $val; }
            elseif ($indexkey == -1 || array_key_exists($indexkey,$value)) {
                $result[($indexkey == -1)?$subarray:$array[$subarray][$indexkey]] = $val;
            }
        }
        return $result;
    }

    public static function timestampToDateTime($timestamp) {
        return date('Y-m-d H:i:s', strtotime($timestamp));
    }

    public static function shortDescription($jsonDescription) {
        $decoded = json_decode($jsonDescription, true);
        return $decoded[0]['description'] ?? '';
    }

    public static function arrayReplaceKey($arr, $oldkey, $newkey) {
        foreach($arr as $k => $value) {
            if(array_key_exists( $oldkey, $value)) {
                $keys = array_keys($value);
                $keys[array_search($oldkey, $keys)] = $newkey;
                $arr[$k] = array_combine($keys, $value);	
            }
        }

        return $arr;    
    }

    public static function getDuration($source) {
        if(preg_match('/\:/', $source) == 1) { 
            $explode = explode(":",$source);
            $hour = (int) $explode[0];
            $minute = (int) ($explode[1] ?? 0);
            $source = ($hour * 3600) + ($minute * 60);
        }

        $minutes = floor($source / 60);
        $hours = floor($minutes / 60);
        $minutes = $minutes - ($hours * 60);

        return ['h' => $hours, 'm' => $minutes];
    }

    public static function formatDuration($duration, $includeZero = false) {
        if(!isset($duration['h']) || !isset($duration['m'])) return '';
        return( $duration['h'] > 0 || $includeZero ? $duration['h'] . ' Jam, ' : '' ) . ( $duration['m'] || $includeZero ? $duration['m'] . ' Menit' : '' );
    }

    public static function createPaginator($totalItems, $itemsPerPage, $currentPage) {
        $maxPagesToShow = 5;
        $parsedUrl = parse_url($_SERVER['REQUEST_URI']);
        $urlPattern = url(!empty($parsedUrl['query']) ? $_SERVER['REQUEST_URI'] . '&page=(:num)' : '?page=(:num)');

        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow($maxPagesToShow);

        return $paginator;
    }

    public static function getPaginationData($data, $limit) {
        $itemsPerPage = !is_null($limit) ? $limit : 12;
        $totalItems = $data['total'];
        $currentPage = $data['current_page'];

        $paginator = self::createPaginator($totalItems, $itemsPerPage, $currentPage);
        $pagination = [
            'total_data' => $data['total'],
            'total_page' => $data['last_page'],
            'current_page' => $data['current_page'],
            'prev_page_url' => $data['prev_page_url'],
            'next_page_url' => $data['next_page_url'],
            'links' => []
        ];

        foreach($paginator->getPages() as $page) {
            $pagination['links'][] = [
                'url' => $page['url'],
                'label' => (string) $page['num'],
                'active' => $page['isCurrent'],
            ];
        }

        return $pagination;
    }
}
