<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class OpenUrl
{
    public static function post(string $url, array $pr = [], bool $comCode = true)
    {
        try {
            // Default parameters
            $param = [
                'company_code' => Config::get('const.set.company_code'),
                'internal_key' => Config::get('const.set.internal_key'),
            ];

            // Remove company_code if $comCode is false
            if (!$comCode) {
                $param = ['internal_key' => Config::get('const.set.internal_key')];
            }

            // Merge additional parameters
            if ($pr) {
                $param = array_merge($param, $pr);
            }

            // Make POST request with retries
            $response = Http::retry(3, 100)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withOptions(['verify' => false])
                ->post(Config::get('const.set.api_url') . $url, $param);

            return $response->object(); // returns as object

        } catch (\Throwable $th) {
            return ['error' => true, 'message' => 'Request failed.'];
        }
    }
}
