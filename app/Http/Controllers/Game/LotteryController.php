<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionService;

use App\Services\LockBonusService;
use App\Services\CurlService;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class CasinoController extends Controller
{

    protected $lockBonusService;
    protected $curlService;

    public function __construct(
        LockBonusService $lockBonusService,
        CurlService $curlService,
    ) {
        $this->lockBonusService = $lockBonusService;
        $this->curlService = $curlService;
    }

    /**
     * List all active users from file-based sessions.
     */
    public function play(Request $request, SessionService $sessionService)
    {

        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $customerId = $user->getId();
        $customerCode = $user->getCustomerCode();
        $username = $user->getUsername();
        $companyName = $user->getCompanyName();
        $companyCode = $user->getCompanyCode();
       
        $groupId = $user->getGroupId();
        $companyId = $user->getCompanyId();
        $accountId  = $user->getAccountId();
        $joinedBonus = $user->getJoinedBonus();
        $authBonusType = strtolower($user->getBonusType());
        $lockBonusDate = strtolower($user->getLockBonusDate());
        $lockBonusAction = strtolower($user->getLockBonusAction());
        $lockBonusDuration = $user->getLockBonusDuration();
        $lockBonusTurnover = $user->getLockBonusTurnover();
        $lockBonusMemberAmount = $user->getLockBonusMemberAmount();
        $lockBonusGetBonus = $user->getLockBonusGetBonus();

        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
            'provider' => 'required|string',
            'device'   => 'required|string|in:MOBILE,DESKTOP',
            'ip' => 'nullable|ip',
            'domain' => 'nullable|url',
            'lobby_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ], 422);

        }

        $category = $request->input('category');
        $provider = $request->input('provider');
        $device = $request->input('device');
        $ip = $request->input('ip');
        $domain = $request->input('domain');
        $lobbyUrl = $request->input('lobby_url');

        $callType = config('constraint.LOCK_BONUS_SLOT');

        $logBonus = [
            'customer_code' => $customerCode,
            'joined_bonus' => $joinedBonus,
            'lock_bonus_action' => $lockBonusAction,
            'auth_bonus_type' => $authBonusType,
            'lock_bonus_date' => $lockBonusDate,
            'call_type' => $callType,
            'lock_bonus_duration' => $lockBonusDuration,
            'lock_bonus_turnover' => $lockBonusTurnover,
            'lock_bonus_member_amount' => $lockBonusMemberAmount,
            'lock_bonus_get_bonus' => $lockBonusGetBonus,
        ];

        $checkLockBonus = $this->lockBonusService->checkLockBonus($logBonus);
        if (!$checkLockBonus['status']) {
            return response()->json([
                "code"    => "500",
                "message" => $checkLockBonus['message'],
                "data"    => $result
            ]);
        }

        $apiGameUrl = config('endPoint.API_GAME_URL');
        
        switch ($provider) {
            case config('providerCode.SK4D'):
                $endpoint = $apiGameUrl . '/sk4d/login';
                $request = [
                    'code' => $customerCode,
                    'game' => config('providerCode.SK4D'),
                    'plateform' => $device ? true : false,
                    'ip' => $ip,
                ];
                $response = $this->curlService->post($endpoint, $request);
                try {
                    $data = [
                        'endpoint' =>  '/' . config('providerCode.SK4D'),
                        'payload' => $response->data->token,
                    ];
                    return response()->json([
                        "code"    => "200",
                        "message" => "Successfully",
                        "data"    => $data
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is under maintenance.",
                        "data"    => null
                    ]);
                }

            case config('providerCode.NEX4D'):
                $endpoint = $apiGameUrl . '/nex4d/playerLogin';
                $request = [
                    'code' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'from_ip' => $ip,
                ];
                $response = $this->curlService->post($endpoint, $request);
                try {
                    if (isset($response->status) && ($response->status == 2)) {
                        $registerUrl = $apiGameUrl . '/nex4d/registerPlayer';
                        $registerResponse = $this->curlService->post($registerUrl, $request);
                        if (isset($registerResponse)) {
                            if (isset($registerResponse->status) && ($registerResponse->status == 1)) {
                                $response = $this->curlService->post($endpoint, $request);
                            }
                        }
                    }
                    $data['endpoint'] = $response->endpoint;
                    return response()->json([
                        "code"    => "200",
                        "message" => "Successfully",
                        "data"    => $data
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is under maintenance.",
                        "data"    => null
                    ]);
                }
            default:
                return response()->json([
                    "code"    => "500",
                    "message" => "Game not found",
                    "data"    => null
                ]);
        }
        
    
    }

    
}
