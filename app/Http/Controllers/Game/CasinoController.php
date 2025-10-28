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
            case config('providerCode.ION'):
                $endpoint = $apiGameUrl . '/ion/login';
                $request = [
                    'AccountId' => $customerCode,
                    'mobile' => $device ? true : false,
                    'product_type' => 'BACCARAT',
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
                
            case config('providerCode.CSBO'):
                $endpoint = $apiGameUrl . '/sbo/login';
                $request = [
                    'player' => $customerCode,
                    'action' => 'Casino',
                    'ismobile' => $mobile ? true : false,
                    'agent_code' => $sboAgent,
                    'provider' => config('providerCode.CSBO'),
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

            case config('providerCode.WM'):
                $endpoint = $apiGameUrl . '/sbo/login';
                $request = [
                    'player' => $customerCode,
                    'action' => 'SeamlessGame',
                    'ismobile' => $device ? true : false,
                    'provider' => config('providerCode.WM'),
                    'game_id' => '0',
                    'gpid' => '0',
                    'agent_code' => $sboAgent,
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

            case config('providerCode.DG'):
                $endpoint = $apiGameUrl . '/dg/login';
                $request = [
                    'code' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'timestamp' => strtotime('now'),
                ];
                $response = $this->curlService->post($endpoint, $request);
                
                try {
                    $dataRegister = [];
                    if ($response->codeId !== 0) {
                        $dataRegister = $this->curlService->post(
                            $apiGameUrl . '/dg/register',
                            $request
                        );
                        
                    }

                    if ($dataRegister) {
                        $response = $this->curlService->post($endpoint, $request);
                    }
                    
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

            case config('providerCode.BG_CASINO'):
                $endpoint = $apiGameUrl . '/bg/login';
                $request = [
                    'loginId' => $customerCode,
                    'isMobileUrl' => $device ? true : false,
                    'fromIp' => $ip,
                    'type' => 'casino',
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

            case config('providerCode.IDN'):
                $endpoint = $apiGameUrl . '/idn-lives/lunch-game';
                $request = [
                    'username' => $customerCode,
                    'currency' => 'IDR',
                    'language' => 'ID',
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

            case config('providerCode.GD88'):
                $endpoint = $apiGameUrl . '/gd88/login';
                $request = [
                    'username' => $customerCode,
                    'lang' => '0',
                    'is_mobile' => $device ? true : false,
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

            case config('providerCode.CCQ9'):
                $endpoint = $apiGameUrl . '/cq9/login';
                $request = [
                    'account' => $customerCode,
                    'gameplat' => $device ? 'MOBILE' : 'WEB',
                    'lang' => 'en',
                    'gamehall' => config('providerCode.CCQ9'),
                    'gamecode' => 'CA01',
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

            case config('providerCode.HG'):
                $endpoint = $apiGameUrl . '/hg/login';
                $request = [
                    'username' => $customerCode
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

            case config('providerCode.CPP'):
                $endpoint = $apiGameUrl . '/pp/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => '101',
                    'platform' => $device ? 'MOBILE' : 'WEB',
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

            case config('providerCode.PPNG'):
                $endpoint = $apiGameUrl . '/ppng/login';
                $request = [
                    'user_code' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'ip' => $ip,
                    'product_type' => 'PINGPONG',
                    'game_type' => 'LOBBY',
                    'language' => 'id-id',
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

            case config('providerCode.LG88'):
                $endpoint = $apiGameUrl . '/lg88/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'from_ip' => $ip
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
