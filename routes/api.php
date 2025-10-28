<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Controllers\BackOffice\ManageSessionController;

// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\AccountController;
// use App\Http\Controllers\DepositController;
// use App\Http\Controllers\BetHistoryController;
// use App\Http\Controllers\LockBonusController;
// use App\Http\Controllers\HomeController;
// use App\Http\Controllers\S3Controller;
//Game Play
// use App\Http\Controllers\Game\SlotController;
// use App\Http\Controllers\Game\CasinoController;
// use App\Http\Controllers\Game\FishingController;
// use App\Http\Controllers\Game\PokerController;
// use App\Http\Controllers\Game\SportController;
// use App\Http\Controllers\Game\CockFightController;
// use App\Http\Controllers\Game\LotteryController;

// Gallery upload first
Route::prefix('dev')->group(function () {
    // Game Play
    // Route::post('/game/slot/play', [SlotController::class, 'play']);
    // Route::post('/game/casino/play', [CasinoController::class, 'play']);
    // Route::post('/game/fishing/play', [FishingController::class, 'play']);
    // Route::post('/game/poker/play', [PokerController::class, 'play']);
    // Route::post('/game/sport/play', [SportController::class, 'play']);
    // Route::post('/game/cockfight/play', [CockFightController::class, 'play']);
    // Route::post('/game/lottery/play', [LotteryController::class, 'play']);

    // Route::post('/gallery/upload/imagebase64', [AuthController::class, 'galleryUploadImage']);
    // Route::post('/gallery/read-image', [AuthController::class, 'readImage']);
    // Route::post('/member/deposit', [AuthController::class, 'memberDeposit']);
    // Route::post('/member/withdraw', [AuthController::class, 'memberWithdraw']);
    // Route::post('/member/deposit/history', [DepositController::class, 'memberDepositHistory']);
    // Route::post('/member/withdraw/history', [AuthController::class, 'memberWithdrawHistory']);
    // Route::post('/member/referral/check', [AuthController::class, 'memberReferralCheck']);
    // Route::post('/member/otp/request', [AuthController::class, 'memberOtpRequest']);
    // Route::post('/member/referral/create', [AuthController::class, 'memberReferralCreate']);
    // Route::post('/member/change/password', [AuthController::class, 'memberChangePassword']);
    // Route::post('/member/referral/list', [AuthController::class, 'memberReferralList']);
    // Route::post('/member/create/account', [AuthController::class, 'createAccount']);
    // Route::post('/member/account/list', [AccountController::class, 'memberAccountList']);
    // Route::post('/vendor/list', [BetHistoryController::class, 'vendorList']);
    // Route::post('/home/provider/category', [HomeController::class, 'providerCategory']); // Not yet used
    // Route::post('/member/bet/history', [BetHistoryController::class, 'memberBetHistory']);
    // Route::post('/is/member/eligible/for/lock/bonus', [LockBonusController::class, 'isMemberEligibleForLockBonus']);
    // Route::post('/member/join/lock/bonus', [LockBonusController::class, 'memberJoinLockBonus']);
    // Route::post('/member/lock/bonus/list', [LockBonusController::class, 'lockBonusList']);
    // Route::post('/s3/image', [S3Controller::class, 'image']);

    Route::post('/list/active/user', [ManageSessionController::class, 'listActiveUser']);
    Route::post('/kickout/user', [ManageSessionController::class, 'kickOutUser']);

    // Route::post('/translations/{code}/{locale}', function($code, $locale) {
    //     // Allowed locales
    //     if (!in_array($locale, ['en','id'])) {
    //         $locale = 'en';
    //     }

    //     App::setLocale($locale);

    //     $translations = [];

    //     // JSON file path: lang/tmp/<code>/<locale>.json
    //     $jsonFile = resource_path("lang/{$code}/{$locale}.json");

    //     if (file_exists($jsonFile)) {
    //         $translations = json_decode(file_get_contents($jsonFile), true);
    //     }

    //     return response()->json($translations);
    // });
});
