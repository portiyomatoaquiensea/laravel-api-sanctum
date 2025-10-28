<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use App\Services\S3Service;

use Carbon\Carbon;
use PDO;

class S3Controller extends Controller
{

    private $s3Service;

    public function __construct(
        S3Service $s3Service,
    ) {
        $this->s3Service = $s3Service;
    }

    public function image(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $validator = Validator::make($request->all(), [
            'path'   => 'required|string'
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                'code' => 404,
                'message' => 'Missing required parameters',
            ], 404);
        }

        $cdnlink = $this->s3Service->cdnLink($companyKey);
        if (!$cdnlink) {
            return response()->json([
                'code' => 404,
                'message' => 'Image not found',
            ], 404);
        }
        
        // https://latte.cdnsimple.top/assets/joker/assets/icon/More.svg
        $s3Domain = rtrim($cdnlink->cdn_url, '/'); // remove trailing slash if any
        $path = ltrim($request->input('path'), '/'); // remove leading slash if any
        $fullUrl = "{$s3Domain}/{$path}";
        // Fetch remote image
        $response = Http::get($fullUrl);

        if (!$response->ok()) {
            return response()->json([
                'code' => 404,
                'message' => 'Image not found',
            ], 404);
        }

        $contentType = $response->header('Content-Type');

        return response($response->body(), 200)
                ->header('Content-Type', $contentType);
    }

   
}
