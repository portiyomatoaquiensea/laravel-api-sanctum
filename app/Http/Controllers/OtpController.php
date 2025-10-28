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
use App\Services\RedisService;

use App\Services\WhatsAppService;
use App\Services\TwilloCodeService;
use Carbon\Carbon;
use PDO;

class OtpController extends Controller
{

    // Redis
    protected $redisService;
    // Service
    protected $whatsAppService;
    protected $twilloCodeService;
    public function __construct(
        RedisService $redisService,

        WhatsAppService $whatsAppService,
        TwilloCodeService $twilloCodeService,
    ) {
        $this->redisService = $redisService;

        $this->whatsAppService = $whatsAppService;
        $this->twilloCodeService = $twilloCodeService;

    }

    public function memberOtpRequest(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }
        $companyCode = $user->getCompanyCode();
        $customerId = $user->getId();

        $validator = Validator::make($request->all(), [
            'phone_number'      => 'required|string',
        ]);
        
        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ]);
        }

        $phoneNumber = $request->input('phone_number');
        $redisKey = "opt/{$companyCode}/{$customerId}/{$phoneNumber}";
        $checkCacheOtp = $this->redisService->get($redisKey);
        if ($checkCacheOtp) {
            $redisExpireIn = $this->redisService->ttl($redisKey);
            $redisExpireInSeconds = $this->redisService->ttlInSeconds($redisKey);
            return response()->json([
                "code"    => "402",
                "message" => 'Otp Already Send please check you Whatapp sms. Expired In '.$redisExpireIn.' menit',
                "data"    => [
                    'expired_in' => $redisExpireInSeconds
                ]
            ]);
        }

        
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $time = Carbon::now()->addMinutes(3)->format('Y-m-d H:i:s');
        $twilloCodeData = [
            'phone_number' => $phoneNumber,
            'code' => $code,
            'expired' => $time,
        ];

        $newTwilloCodeId = $this->twilloCodeService->insert($twilloCodeData);
        if (!$newTwilloCodeId) {
            return response()->json([
                'code'    => '404',
                'message' => 'Failed to create OTP',
                'data'    => $referral,
            ]);
        }
       
        $jsonCache = json_encode($twilloCodeData);
        // Store the response in Redis for 300 seconds = 5mn
        $this->redisService->set($redisKey, $jsonCache, 180);

        // Remove Otp from response
        unset($twilloCodeData['code']);
        $twilloCodeData['expired_in'] = (string)180;
        
        $sendOtp = $this->whatsAppService->sendOtp([
            'message' => 'Your verification code: ' . $code,
            'phone_number' => $phoneNumber,
        ]);

        $message = $sendOtp->sent 
            ? 'Please check OTP on your phone.' 
            : ($sendOtp->message ?? 'Error on Whatsapp service');

        return response()->json([
            'code' => '200',
            'message' => $message,
            'data' => $twilloCodeData,
        ]);
        
    }


}
