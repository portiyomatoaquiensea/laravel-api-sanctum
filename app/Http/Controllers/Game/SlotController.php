<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionService;

use App\Services\LockBonusService;
use App\Services\X88Service;
use App\Services\GameService;
use App\Services\CurlService;
use App\Services\CustomerGameIdService;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class SlotController extends Controller
{

    protected $lockBonusService;
    protected $x88Service;
    protected $gameService;
    protected $curlService;
    protected $customerGameIdService;

    public function __construct(
        LockBonusService $lockBonusService,
        X88Service $x88Service,
        GameService $gameService,
        CurlService $curlService,
        CustomerGameIdService $customerGameIdService,
    ) {
        $this->lockBonusService = $lockBonusService;
        $this->x88Service = $x88Service;
        $this->gameService = $gameService;
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
                "data"    => null
            ]);
        }

        $apiGameUrl = config('endPoint.API_GAME_URL');
        $apiGameUrlX88 = config('endPoint.API_GAME_URL_X88');

        $game = $this->gameService->active(
            $provider,
            $gameId
        );
        
        if (!$game) {
            return response()->json([
                "code"    => "500",
                "message" => "Game is not available.",
                "data"    => null
            ]);
        }

        switch ($provider) {
            case config('providerCode.FG'):
                $endpoint = $apiGameUrl . '/sbo/login';
                $request = [
                    'player' => $customerCode,
                    'action' => 'SeamlessGame',
                    'ismobile' => $device ? true : false,
                    'game_id' => $gameId,
                    'gpid' => '36',
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

            case config('providerCode.PS'):
            case config('providerCode.SPS'):
                $endpoint = $apiGameUrl . '/ps/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $device ? true : false,
                    'game_id' => $gameId,
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

            case config('providerCode.PP'):
                // Start of X88 (XPP)
                $x88CompanyActive = $this->x88Service->companyActive(
                    $companyId,
                    config('providerCode.XPP')
                );
                
                if ($x88CompanyActive) {
                    $xppRtp = $x88CompanyActive['rtp'];
                    $x88Game = $this->gameService->active(
                        config('providerCode.XPP'),
                        $gameId
                    );

                    if ($x88Game) {

                        $endpoint = $apiGameUrlX88 . '/x88/login';
                        $xppGameId = $x88Game['game_id'];
                        $request = [
                            'user_code' => $customerCode,
                            'game_id' => $xppGameId,
                            'provider' => config('providerCode.XPP'),
                            'provider_code' => 'PRAGMATIC',
                        ];
                        $xppLogin = $this->curlService->postX88($endpoint, $request);

                        try {
                            $endpointUserRtp = $apiGameUrlX88 . '/x88/user_rtp';
                            $requestRtp = [
                                'user_code' => $customerCode,
                                'provider' => 'PRAGMATIC',
                                'rtp' => $xppRtp,
                            ];
                            $responseUserRtp = $this->curlService->postX88($endpointUserRtp, $requestRtp);
                            $data['endpoint'] = $xppLogin->url ?? '';
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
                    }
                }

                // End of X88 (XPP)
                $endpoint = $apiGameUrl . '/pp/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => $gameId,
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

            case config('providerCode.HB'):
                // Start of X88 (XHABAN)
                $x88CompanyActive = $this->x88Service->companyActive(
                    $companyId,
                    config('providerCode.XHABAN')
                );

                if ($x88CompanyActive) {
                    $xppRtp = $x88CompanyActive['rtp'];
                    $x88Game = $this->gameService->active(
                        config('providerCode.XHABAN'),
                        $gameId
                    );

                    if ($x88Game) {
                        $endpoint = $apiGameUrlX88 . '/x88/login';
                        $xppGameId = $x88Game['game_id'];
                        $request = [
                            'user_code' => $customerCode,
                            'game_id' => $xppGameId,
                            'provider' => config('providerCode.XHABAN'),
                            'provider_code' => 'HABANERO',
                        ];
                        $xppLogin = $this->curlService->postX88($endpoint, $request);
                        try {
                            $endpointUserRtp = $apiGameUrlX88 . '/x88/user_rtp';
                            $requestRtp = [
                                'user_code' => $customerCode,
                                'provider' => 'HABANERO',
                                'rtp' => $xppRtp,
                            ];
                            $responseUserRtp = $this->curlService->postX88($endpointUserRtp, $requestRtp);
                            $data['endpoint'] = $xppLogin->url ?? '';
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
                    }
                }
                
                $endpoint = $apiGameUrl . '/haban/login';
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => $gameId,
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

            case config('providerCode.RTG'):
            case config('providerCode.SRTG'):
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

            case config('providerCode.SG'):
            case config('providerCode.SSG'):
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

            case config('providerCode.GW'):
                $endpoint = $apiGameUrl . '/gw/login';
                $request = [
                    'player' => $customerCode,
                    'game_id' => $gameId,
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

            case config('providerCode.CQ9'):
            case config('providerCode.SLCQ9'):
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

            case config('providerCode.MG'):
                $endpoint = $apiGameUrl . '/mg/mglogin';
                $request = [
                    'player' => $customerCode,
                    'code' => $gameId,
                    'provider' => config('providerCode.MG'),
                    'mobile' => $device ? true : false,
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

            case config('providerCode.S88'):
                $endpoint = $apiGameUrl . '/s88/login';
                $s88Password = $this->customerGameIdService->password($customerId, $provider);
                if (!$s88Password) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is under maintenance.",
                        "data"    => null
                    ]);
                }

                $encryptKey = $this->curlService->decrypt($s88Password['encrypt_key']);
                $request = [
                    'username' => $customerCode,
                    'password' => $encryptKey,
                    'game_code' => $gameId,
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

            case config('providerCode.JL'):
            case config('providerCode.SJL'):
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

            case config('providerCode.FS'):
            case config('providerCode.SFS'):
                $endpoint = $apiGameUrl . '/fs/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $mobile ? 1 : 0,
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

            case config('providerCode.JKS'):
            case config('providerCode.SJKS'):
                $endpoint = $apiGameUrl . '/jk/login';
                $request = [
                    'username' => $customerCode,
                    'game_id' => $gameId,
                    'isMobile' => $mobile ? true : false,
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

            case config('providerCode.PG'):
                // Start of X88 (XPG)
                $x88CompanyActive = $this->x88Service->companyActive(
                    $companyId,
                    config('providerCode.XPG')
                );
                if ($x88CompanyActive) {
                    $xpgRtp = $x88CompanyActive['rtp'];
                    $x88Game = $this->gameService->active(
                        config('providerCode.XPG'),
                        $gameId
                    );

                    if ($x88Game) {
                        $endpoint = $apiGameUrlX88 . '/x88/login';
                        $xpgGameId = $x88Game['game_id'];
                        $request = [
                            'user_code' => $customerCode,
                            'game_id' => $xpgGameId,
                            'provider' => config('providerCode.XPG'),
                            'provider_code' => 'PGSOFT',
                        ];
                        $xpgLogin = $this->curlService->postX88($endpoint, $request);
                        try {
                            $endpointUserRtp = $apiGameUrlX88 . '/x88/user_rtp';
                            $requestRtp = [
                                'user_code' => $customerCode,
                                'provider' => 'PGSOFT',
                                'rtp' => $xpgRtp,
                            ];
                            $responseUserRtp = $this->curlService->postX88($endpointUserRtp, $requestRtp);
                            $data['endpoint'] = $xpgLogin->url ?? '';
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
                    }
                }
                // End of X88 (XPG)

                $endpoint = $apiGameUrl . '/pg/login';
                $request = [
                    'player' => $customerCode,
                    'game_code' => $gameId,
                    'ip' => $ip,
                ];
                $response = $this->curlService->post($endpoint, $request);

                try {
                    $data['html'] = $response->url ?? '';
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

            case config('providerCode.SAFB'):
            case config('providerCode.SSAFB'):
                $endpoint = $apiGameUrl . '/safb/login';
                $request = [
                    'username' => $customerCode,
                    'is_mobile' => $mobile ? true : false,
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

            case config('providerCode.TGS'):
            case config('providerCode.STGS'):
                $endpoint = $apiGameUrl . '/tgs/tangkas';
                $request = [
                    'username' => $customerCode,
                    'mobile' => $mobile ? 'MOBILE' : 'DESKTOP',
                    'type' => 'slot',
                    'game_id' => $gameId
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
            case config('providerCode.BS'):
                $endpoint = $apiGameUrl . '/bs/login';
                $params = [
                    'username' => $customerCode,
                    'mobile' => $mobile ? 'MOBILE' : 'DESKTOP',
                    'game_id' => $gameId,
                    'lobby_url' => $lobbyUrl,
                ];
                $response = $this->curlService->post($endpoint, $params);

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

            case config('providerCode.XPP'):
            case config('providerCode.XPG'):
            case config('providerCode.XTOPTR'):
            case config('providerCode.XPLSON'):
            case config('providerCode.XBONGO'):
            case config('providerCode.XHASAW'):
            case config('providerCode.XSPB'):
            case config('providerCode.XGENE'):
            case config('providerCode.XNAGA'):
            case config('providerCode.XGMW'):
            case config('providerCode.XEVO'):
                $x88CompanyActive = $this->x88Service->companyActive(
                    $companyId,
                    $provider
                );

                if (!$x88CompanyActive) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is under maintenance.",
                        "data"    => null
                    ]);
                }
                $xppRtp = $x88CompanyActive['rtp'];
                $x88Game = $this->gameService->active(
                    $provider,
                    $gameId
                );
                if (!$x88Game) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is not available.",
                        "data"    => null
                    ]);
                }

                $x88Code = $this->x88Service->x88Code($provider);
                if (!$x88Code) {
                    return response()->json([
                        "code"    => "500",
                        "message" => "Game is not available.",
                        "data"    => null
                    ]);
                }
                $endpoint = $apiGameUrlX88 . '/x88/login';
                $xppGameId = $x88Game['game_id'];
                $request = [
                    'user_code' => $customerCode,
                    'game_id' => $xppGameId,
                    'provider' => $provider,
                    'provider_code' => $x88Code,
                ];
                
                $xppLogin = $this->curlService->postX88($endpoint, $request);
                
                try {
                    $endpointUserRtp = $apiGameUrlX88 . '/x88/user_rtp';
                    $requestRtp = [
                        'user_code' => $customerCode,
                        'provider' => $x88Code,
                        'rtp' => $xppRtp,
                    ];
                    $responseUserRtp = $this->curlService->postX88($endpointUserRtp, $requestRtp);
                    
                    $data['endpoint'] = $xppLogin->url ?? '';
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
