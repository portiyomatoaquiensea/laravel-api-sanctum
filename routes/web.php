<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\S3Controller;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BetHistoryController;
use App\Http\Controllers\LockBonusController;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\AmpController;

use App\Http\Controllers\Game\SlotController;
use App\Http\Controllers\Game\CasinoController;
use App\Http\Controllers\Game\FishingController;
use App\Http\Controllers\Game\PokerController;
use App\Http\Controllers\Game\SportController;
use App\Http\Controllers\Game\CockFightController;
use App\Http\Controllers\Game\LotteryController;

Route::get('/', function () {
    return view('welcome');
});

/* Start Route Guard By Auth */

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/user', [AuthController::class, 'me']);
Route::post('/member/change/password', [AuthController::class, 'memberChangePassword']);
Route::post('/member/balance', [AuthController::class, 'memberBalance']);

// Game Play
Route::post('/game/slot/play', [SlotController::class, 'play']);
Route::post('/game/casino/play', [CasinoController::class, 'play']);
Route::post('/game/fishing/play', [FishingController::class, 'play']);
Route::post('/game/poker/play', [PokerController::class, 'play']);
Route::post('/game/sport/play', [SportController::class, 'play']);
Route::post('/game/cockfight/play', [CockFightController::class, 'play']);
Route::post('/game/lottery/play', [LotteryController::class, 'play']);

// Account
Route::post('/member/create/account', [AccountController::class, 'createAccount']);
Route::post('/member/account/list', [AccountController::class, 'memberAccountList']);
Route::post('/member/account', [AccountController::class, 'memberAccount']);
Route::post('/operator/account', [AccountController::class, 'operatorAccount']);

// Deposit
Route::post('/member/deposit/history', [DepositController::class, 'memberDepositHistory']);
Route::post('/account/deposit', [DepositController::class, 'deposit']);
Route::post('/member/deposit', [DepositController::class, 'memberDeposit']);

// Withdraw
Route::post('/member/withdraw/history', [WithdrawController::class, 'memberWithdrawHistory']);
Route::post('/member/withdraw', [WithdrawController::class, 'memberWithdraw']);

// OTP
Route::post('/member/otp/request', [OtpController::class, 'memberOtpRequest']);

// Referral
Route::post('/member/referral/check', [ReferralController::class, 'memberReferralCheck']);
Route::post('/member/referral/create', [ReferralController::class, 'memberReferralCreate']);
Route::post('/member/referral/list', [ReferralController::class, 'memberReferralList']);

// Bethistory
Route::post('/vendor/list', [BetHistoryController::class, 'vendorList']);
Route::post('/member/bet/history', [BetHistoryController::class, 'memberBetHistory']);

// Lock Bonus
Route::post('/is/member/eligible/for/lock/bonus', [LockBonusController::class, 'isMemberEligibleForLockBonus']);
Route::post('/member/join/lock/bonus', [LockBonusController::class, 'memberJoinLockBonus']);
Route::post('/member/lock/bonus/list', [LockBonusController::class, 'lockBonusList']);

// Gallery
Route::post('/gallery/upload/imagebase64', [GalleryController::class, 'galleryUploadImage']);
Route::post('/gallery/read-image', [GalleryController::class, 'readImage']);

// S3
Route::post('/s3/image', [S3Controller::class, 'image']);
/* End Route Guard By Auth */

// Seo
Route::post('/robots-txt', [SeoController::class, 'robotText']);
Route::post('/sitemap-xml', [SeoController::class, 'sitemap']);
Route::post('/general/setting', [SeoController::class, 'generalSetting']);
Route::post('/page/meta', [SeoController::class, 'pageMeta']);

// Amp
Route::post('/amp', [AmpController::class, 'index']);

// Home
Route::post('/home/slider', [HomeController::class, 'slider']);
Route::post('/home/bonus', [HomeController::class, 'bonus']);
Route::post('/home/promotion', [HomeController::class, 'promotion']);
Route::post('/home/bank', [HomeController::class, 'bank']);
Route::post('/home/contact', [HomeController::class, 'contact']);
Route::post('/home/provider/category', [HomeController::class, 'providerCategory']); // Not yet used
Route::post('/home/popular/game', [HomeController::class, 'popularGame']);
Route::post('/home/menu/mega', [HomeController::class, 'megaMenu']);
Route::post('/home/menu/mega/game', [HomeController::class, 'megaMenuGame']);

// Game
Route::post('/game/slot', [GameController::class, 'slot']);

// Translate
Route::post('/translations/{code}/{locale}', function($code, $locale) {
    // Allowed locales
    if (!in_array($locale, ['en','id'])) {
        $locale = 'en';
    }

    App::setLocale($locale);

    $translations = [];

    // JSON file path: lang/tmp/<code>/<locale>.json
    $jsonFile = resource_path("lang/{$code}/{$locale}.json");

    if (file_exists($jsonFile)) {
        $translations = json_decode(file_get_contents($jsonFile), true);
    }

    return response()->json($translations);
});