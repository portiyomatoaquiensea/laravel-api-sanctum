<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

use App\Services\SessionService; // Session Service

use App\Services\GalleryService;
use Carbon\Carbon;
use PDO;

class GalleryController extends Controller
{

    // Service
    protected $galleryService;
    public function __construct(
        GalleryService $galleryService,
    ) {
        $this->galleryService = $galleryService;
    }

    public function galleryUploadImage(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }
        $companyId = $user->getCompanyId();
        $companyCode = $user->getCompanyCode();
        $customerId = $user->getId();
        if (!$request->hasFile('file')) {
            return response()->json([
                'code'    => '422',
                'message' => 'File not uploaded or wrong input name',
                'data'    => null,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,gif|max:10240',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json([
                'code'    => '422',
                'message' => 'Missing or invalid parameters',
                'data'    => $errors,
            ]);
        }

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $fileContent = file_get_contents($file->getPathname());
        $base64 = base64_encode($fileContent);

        $uploadRequest = [
            'company_code' => $companyCode,
            'image' => $base64,
            'path' => 'deposit',
            'file_name' => $filename,
        ];
        $result = $this->galleryService->uploadBase64($uploadRequest);
        if (!$result || $result->code !== 200) {
            return response()->json([
                'code'    => '500',
                'message' => 'Failed to upload image',
                'data'    => null,
            ], 500);
        }
        // https://api-u3.suka-dev.com/galleries/read3?path=deposit&package=tmp&image=10_2025/logo.png
        return response()->json([
            'code'    => '200',
            'message' => 'Image uploaded successfully',
            'data'    => $result,
        ]);
    }

    public function readImage(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                'code' => 403,
                'message' => 'Please login first',
            ], 403);
        }
        $companyId = $user->getCompanyId();
        $companyCode = $user->getCompanyCode();
        $customerId = $user->getId();

        $validator = Validator::make($request->all(), [
            'path'   => 'required|string',
            'image'=> 'required|string',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                'code' => 422,
                'message' => 'Missing required parameters',
            ], 422);
        }

        $path = $request->input('path');
        $image = $request->input('image');
        $remoteUrl = $this->galleryService->readUrl($path, $companyCode, $image);

        // Fetch remote image
        $response = Http::get($remoteUrl);

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
