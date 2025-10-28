<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionService;

use App\Services\LockBonusService;
use App\Services\CurlService;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class SportController extends Controller
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
        $sboAgent = trim($user->getSboAgent());
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
            case config('providerCode.SBO'):
            case config('providerCode.VSBO'):
                $endpoint = $apiGameUrl . '/sbo/login';
                $request = [
                    'player' => $customerCode,
                    'ismobile' => $device ? true : false,
                    'agent_code' => $sboAgent,
                    'action' => ($provider === config('providerCode.SBO')) ? 'SportsBook' : 'VirtualSports',
                    'provider' => $provider,
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
            
            case config('providerCode.CMD'):
                $endpoint = $apiGameUrl . '/cmd/login';
                $request = [
                    'player' => $customerCode,
                    'ismobile' => $device ? true : false,
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

            case config('providerCode.SCQ9'):
                $endpoint = $apiGameUrl . '/cq9/login';
                $request = [
                    'gameplat' => $device ? 'WEB' : 'MOBILE',
                    'lang' => 'en',
                    'gamehall' => config('providerCode.CQ9'),
                    'gamecode' => 'BPUP2019',
                    'account' => $customerCode,
                ];
                $response = $this->curlService->get($endpoint, $request);

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

            case config('providerCode.VSPP'):
                $endpoint = $apiGameUrl . '/pp/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => 'vppso4',
                    'platform' => $device ? 'WEB' : 'MOBILE',
                    'technology' => 'H5',
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

            case config('providerCode.SABA'):
                $endpoint = $apiGameUrl . '/saba/login';
                $request = [
                    'code' => $customerCode,
                    'product_code' => config('providerCode.SABA'),
                    'mobile' => $device ? true : false,
                    'game_id' => '',
                    'product_id' => '',
                ];

                $response = $this->curlService->post($endpoint, $request);
                
                try {
                    $data['endpoint'] = isset($response->url) ? $response->url : '';
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

            case config('providerCode.AFB'):
                $endpoint = $apiGameUrl . '/afb/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $device ? 1 : 0,
                    'game_id' => '',
                    'lobby_url' => '',
                ];
                $response = $this->curlService->post($endpoint, $request);
                
                try {
                    $data['endpoint'] = $response->endpoint ?? '';
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
