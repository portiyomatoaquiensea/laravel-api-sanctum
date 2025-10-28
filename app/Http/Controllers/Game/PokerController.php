<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionService;

use App\Services\LockBonusService;
use App\Services\CurlService;
use App\Services\CustomerGameIdService;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class PokerController extends Controller
{

    protected $lockBonusService;
    protected $curlService;
    protected $customerGameIdService;

    public function __construct(
        LockBonusService $lockBonusService,
        CurlService $curlService,
        CustomerGameIdService $customerGameIdService,
    ) {
        $this->lockBonusService = $lockBonusService;
        $this->curlService = $curlService;
        $this->customerGameIdService = $customerGameIdService;
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
            case config('providerCode.ONEPOCKER'):
                $endpoint = $apiGameUrl . '/one_gaming/login';
                $request = [
                    'AccountId' => $customerCode,
                    'mobile' => $device ? 'MOBILE' : 'DESKTOP',
                    'product_type' => 'ONEPOKER',
                ];
                $response = $this->curlService->post($endpoint, $request);
                try {
                    $data['endpoint'] = $response->url ?? '';
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

            case config('providerCode.BLKP'):
                $endpoint = $apiGameUrl . '/balak-play/login';
                $blkpPassword = $this->customerGameIdService->password($customerId, $provider);
                if (!$blkpPassword) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is under maintenance.",
                        "data"    => null
                    ]);
                }

                $encryptKey = $this->curlService->decrypt($blkpPassword['encrypt_key']);
                $request = [
                    'username' => $customerCode,
                    'password' => $encryptKey,
                    'mobile' => $device ? 'MOBILE' : '',
                ];
                $response = $this->curlService->post($endpoint, $request);
                try {
                    $data['endpoint'] = $response->url ?? '';
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

            case config('providerCode.PTGS'):
                $endpoint = $apiGameUrl . '/tgs/tangkas';
                $request = [
                    'mobile' => $device ? 'MOBILE' : 'DESKTOP',
                    'username' => $customerCode,
                    'game_id' => 2,
                    'type' => 'poker',
                ];
                $response = $this->curlService->post($endpoint, $request);
                
                try {
                    $data['endpoint'] = $response->url ?? '';
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
            case config('providerCode.WPK'):
                $endpoint = $apiGameUrl . '/wpk/login';
                $request = [
                    'username' => $customerCode,
                ];
                $response = $this->curlService->post($endpoint, $request);
                
                try {
                    $data['endpoint'] = $response->url ?? '';
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
