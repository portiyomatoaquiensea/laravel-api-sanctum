<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionService;

use App\Services\LockBonusService;
use App\Services\X88Service;
use App\Services\GameService;
use App\Services\CurlService;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class FishingController extends Controller
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
            'game_id' => 'required|string',
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
        $gameId = $request->input('game_id');
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
            case config('providerCode.BG_FISHING'):
                $endpoint = $apiGameUrl . '/bg/login';
                $request = [
                    'loginId' => $customerCode,
                    'isMobileUrl' => $device ? true : false,
                    'lang' => 'en-US',
                    'fromIp' => $ip,
                    'type' => 'fishing',
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

            case config('providerCode.FRTG'):
                $endpoint = $apiGameUrl . '/rtg/login';
                $request = [
                    'player' => $customerCode,
                    'game_id' => $gameId,
                    'lobby_url' => $lobbyUrl,
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

            case config('providerCode.SG_FISHING'):
                $endpoint = $apiGameUrl . '/sg/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => $gameId,
                    'lobby_url' => '/',
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

            case config('providerCode.FCQ9'):
                $endpoint = $apiGameUrl . '/cq9/login';
                $request = [
                    'account' => $customerCode,
                    'lang' => 'en',
                    'gameplat' => $device ? 'MOBILE' : 'WEB',
                    'gamehall' => config('providerCode.CQ9'),
                    'gamecode' => $gameId,
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

            case config('providerCode.FPP'):
                $endpoint = $apiGameUrl . '/pp/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => $gameId,
                    'technology_id' => 'H5',
                    'platform' => $device ? 'MOBILE' : 'WEB',
                    'lobby_url' => '',
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

            case config('providerCode.FFS'):
                $endpoint = $apiGameUrl . '/fs/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $device ? 1 : 0,
                    'game' => $gameId,
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

            case config('providerCode.FJL'):
                $endpoint = $apiGameUrl . '/jl/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => $gameId,
                    'platform' => $device ? 'MOBILE' : 'WEB',
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

            case config('providerCode.FPS'):
                $endpoint = $apiGameUrl . '/ps/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'game' => $gameId,
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

            case config('providerCode.FJKS'):
                $endpoint = $apiGameUrl . '/jk/login';
                $request = [
                    'username' => $customerCode,
                    'game_id' => $gameId,
                    'isMobile' => $device ? true : false,
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

            case config('providerCode.FAFB'):
                $endpoint = $apiGameUrl . '/safb/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'game_id' => $gameId,
                    'lobby_url' => $lobbyUrl,
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
