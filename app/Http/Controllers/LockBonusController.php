<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

use App\Services\SessionService;

use App\Services\LockBonusService; 
use App\Services\CustomerService;
use App\Services\AccountService;
use App\Services\TransactionService;
use App\Services\DailyWinloseService;

use Carbon\Carbon;
use PDO;

class LockBonusController extends Controller
{

    protected $lockBonusService;
    protected $customerService;
    protected $accountService;
    protected $transactionService;
    protected $dailyWinloseService;
    public function __construct(
        LockBonusService $lockBonusService,
        CustomerService $customerService,
        AccountService $accountService,
        TransactionService $transactionService,
        DailyWinloseService $dailyWinloseService,
    ) {
        $this->lockBonusService = $lockBonusService;
        $this->customerService = $customerService;
        $this->accountService = $accountService;
        $this->transactionService = $transactionService;
        $this->dailyWinloseService = $dailyWinloseService;
    }

    public function isMemberEligibleForLockBonus(Request $request, SessionService $sessionService)
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
        $accountId = $user->getAccountId();
       
        $queryString = "
            SELECT 
                id,
                bonus_type,
                is_bonus,
                regiser_date
            FROM accounts WITH (NOLOCK)
            WHERE id = :account_id
            AND company_id = :company_id
            AND main = 1
            AND deleted = 0
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($queryString);

        $stmt->bindParam(':account_id', $accountId, PDO::PARAM_STR);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result || !isset($result['regiser_date'])) {
            return response()->json([
                "code"    => "404",
                "message" => "Account not found",
                "data"    => null
            ]);
        }
        $registerDate = $result['regiser_date'];

        $queryComLockBonus = "
            SELECT 
                id,
                start_date
            FROM lock_bonus_companies WITH (NOLOCK)
            WHERE company_id = :company_id
        ";

        $stmtComLockBonus = DB::connection('main')->getPdo()->prepare($queryComLockBonus);

        $stmtComLockBonus->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtComLockBonus->execute();
        $comLockBonus = $stmtComLockBonus->fetch(PDO::FETCH_ASSOC);

        if (!$comLockBonus) {
            return response()->json([
                "code"    => "404",
                "message" => "Company not found",
                "data"    => null
            ]);
        }
        $startDate = $comLockBonus['start_date'];

        $registerDate = Carbon::parse($registerDate);
        $startDate = Carbon::parse($startDate);
        
        if ($startDate->gt($registerDate)) {
            return response()->json([
                "code"    => "404",
                "message" => "Member Is Not Eligible For LockBonus",
                "data"    => null
            ]);
        }

        return response()->json([
            "code" => "200",
            "message" => "Member Is Eligible For LockBonus",
            "data" => [
                'start' => $startDate,
                'register' => $registerDate,
                '$accountId' => $accountId
            ]
        ]);
    
    }

    public function memberJoinLockBonus(Request $request, SessionService $sessionService)
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
        $groupId = $user->getGroupId();
        $companyId = $user->getCompanyId();
        $accountId  = $user->getAccountId();
        $joinedBonus = $user->getJoinedBonus();
        $authBonusType = $user->getBonusType();
    
        if ($joinedBonus == "YES") {
            return response()->json([
                "code"    => "422",
                "message" => "You Already join ".$authBonusType." Bonus",
                "data"    => null
            ]);
        }

        $allowedBonusTypes = array_values($this->lockBonusService->categories());
        // ✅ Validation (supports datetime)
        $validator = Validator::make($request->all(), [
            'bonus_type' => 'required|string|in:' . implode(',', $allowedBonusTypes),
            'amount'  => 'required|numeric|min:1',
            'ip' => 'nullable|ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "code"    => "422",
                "message" => "Missing or invalid parameters",
                "data"    => null
            ]);
        }

        $amount = $request->input('amount');
        $bonusType = $request->input('bonus_type');
        $ip = $request->input('ip');

        $queryAcc = "SELECT TOP 1
            id, 
            customer_id,
            company_id,
            balance,
            bonus
            FROM accounts WITH (NOLOCK)
            WHERE id = :account_id
            AND customer_id = :customer_id
            AND company_id = :company_id";

        $stmtAcc = DB::connection('main')->getPdo()->prepare($queryAcc);
        $stmtAcc->bindParam(':account_id', $accountId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtAcc->execute();
        $accounts = $stmtAcc->fetch(PDO::FETCH_ASSOC);
        if (!$accounts) {
            return response()->json([
                "code"    => "404",
                "message" => "Account not found",
                "data"    => null
            ]);
        }
        $balance = $accounts['balance'];
        $bonus = $accounts['bonus'];

        $queryFdp = "
            SELECT TOP 1
            amount
            FROM game_plays WITH (NOLOCK)
            WHERE customer_id = :customer_id
        ";

        $stmtFdp = DB::connection('main')->getPdo()->prepare($queryFdp);
        $stmtFdp->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtFdp->execute();
        $firstDeposit = $stmtFdp->fetch(PDO::FETCH_ASSOC);
        if (!$firstDeposit) {
            return response()->json([
                "code"    => "500",
                "message" => "silahkan melakukan deposit pertama anda",
                "data"    => null
            ]);
        }

        $fdpAmount = $firstDeposit['amount'];
        $lockbonusSetting = $this->lockBonusService->setting($companyId, $bonusType);
        if (!$lockbonusSetting) {
            return response()->json([
                "code"    => "500",
                "message" => "Lock Bonus setting not Active",
                "data"    => null
            ]);
        }

        $settingJoinAmount = $lockbonusSetting['join_amount'];
        $settingPayoutAmount = $lockbonusSetting['payout_amount'];
        $settingTurnover = $lockbonusSetting['turnover'];
        $settingDuration = $lockbonusSetting['duration'];
        $settingStatus = $lockbonusSetting['status'];

        if ($amount < $settingJoinAmount || $amount > $settingPayoutAmount) {
             return response()->json([
                "code"    => "500",
                "message" => 'Minimum Join Amount is '
                    .number_format($settingJoinAmount)
                    .' and Maximum Join Amount is '
                    .number_format($settingPayoutAmount),
                "data"    => null
            ]);
        }
        $reportData = [
            'first_deposit' => $fdpAmount,
            'join_amount' => $amount,
        ];
        $settingBonus = $lockbonusSetting['bonus'];
        $bonusAmount = ($amount * ($settingBonus / 100));
        $bonusBalance = $amount + $bonusAmount;
        $beginBonus = $bonus + $amount;
        $lastBalance = $balance - $amount;
        $getBonus = $bonusBalance - $amount;
        
        $lockBonusLogData = [
            'customer_id' => $customerId,
            'company_id' => $companyId,
            'user_id' => '',
            'begin_bonus' => $beginBonus,
            'end_bonus' => $bonusBalance,
            'join_bonus_amount' => $amount,
            'given_bonus_amount' => $lastBalance,
            'bonus_type' => $bonusType,
            'setting' => json_encode([
                'company_id' => $companyId,
                'member_amount' => $amount,
                'get_bonus' => $getBonus,
                'total_bonus' => $bonusBalance,
                'end_balance' => $lastBalance,
                'join_amount' => $settingJoinAmount,
                'bonus' => $settingBonus,
                'payout_amount' => $settingPayoutAmount,
                'turnover' => $settingTurnover,
                'duration' => $settingDuration,
                'category_type' => $bonusType,
                'status' => $settingStatus,
            ]),
            'action' => config('constraint.LOCK_BONUS_LOG_JOIN')
        ];
        $saveLockBonusLog = $this->lockBonusService->saveLockBonusLog($lockBonusLogData);
        if (!$saveLockBonusLog) {
            return response()->json([
                "code"    => "500",
                "message" => "Error Save Lock Bonus Log",
                "data"    => null
            ]);
        }
        $updateCusJoinBonus = $this->customerService->updateJoinBonus($customerId);
        if (!$updateCusJoinBonus) {
            return response()->json([
                "code"    => "500",
                "message" => "Error Update member to bonus",
                "data"    => null
            ]);
        }
        $updateAccountData = [
            'account_id' => $accountId,
            'last_bonus' => $bonusBalance,
            'last_balance' => $lastBalance,
            'amount' => $amount,
            'bonus_type' => $bonusType,
        ];
        $updateAccJoinBonus = $this->accountService->updateJoinBonus($updateAccountData);
        if (!$updateAccJoinBonus) {
            return response()->json([
                "code"    => "500",
                "message" => "Error Update account bonus",
                "data"    => null
            ]);
        }
        
        $transactionData = [
            'account_id' => $accountId,
            'begin_balance' => $balance,
            'end_balance' => $lastBalance,
            'amount' => $amount,
            'ip_address' => $ip,
            'type' => 'join bonus',
            'status' => config('constraint.STATUS_APPROVED'),
            'description' => 'JOIN BONUS',
        ];
        $insertTransaction = $this->transactionService->insert($transactionData);
        if (!$insertTransaction) {
            return response()->json([
                "code"    => "500",
                "message" => "Error Insert Transaction",
                "data"    => null
            ]);
        }
        $joinDate = Carbon::now()->format('Y-m-d');
        
        $lockBonusReportData = [
            'group_id' => $groupId,
            'company_id' => $companyId,
            'company_name' => $companyName,
            'customer_id' => $customerId,
            'customer_username' => $username,
            'customer_code' => $customerCode,
            'bonus_type' => $bonusType,
            'join_amount' => $amount,
            'turnover' => 0,
            'given_amount' => $bonusAmount,
            'claim_amount' => 0,
            'first_deposit' => $fdpAmount,
            'join_date' => $joinDate,
            'claim_type' => config('constraint.LOCK_BONUS_LOG_JOIN'),
        ];
        
        $saveLockBonusReport = $this->lockBonusService->saveLockBonusReport($lockBonusReportData);
        if (!$saveLockBonusReport) {
            return response()->json([
                "code"    => "500",
                "message" => "Error Save Lock Bonus Report",
                "data"    => null
            ]);
        }
        $lockBonusDate = Carbon::now()->format('Y-m-d H:i:s');
        // Update auth user for join bonus
        $user->setJoinedBonus('JOINED');
        $user->setBonusType($bonusType);
        $user->setLockBonusDate($lockBonusDate);

        $user->setLockBonusDuration($settingDuration);
        $user->setLockBonusTurnover($settingTurnover);
        $user->setLockBonusMemberAmount($amount);
        $user->setLockBonusGetBonus($getBonus);

        // ✅ Save updated user back to session
        $sessionService->setUser($request, $user);

        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => null
        ]);
    }

    public function lockBonusList(Request $request, SessionService $sessionService)
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
        $companyId = $user->getCompanyId();
        $companyCode  = $user->getCompanyCode();
        $accountId = $user->getAccountId();
        $authBonusType  = $user->getBonusType();
        $authLockBonusDate  = $user->getLockBonusDate();

        $expired = false;
        $progress = 0;
        $accountbonus = 0;
        $memberTurnover = 0;
        $memberBonusTurnover = 0;

        $settingList = $this->lockBonusService->settingList($companyId);
        if (!$settingList) {
            return response()->json([
                "code"    => "404",
                "message" => "Lock Bonus is not available",
                "data"    => null
            ]);
        }

        $queryAcc = "SELECT TOP 1
            bonus
            FROM accounts WITH (NOLOCK)
            WHERE id = :account_id
            AND customer_id = :customer_id
            AND company_id = :company_id";

        $stmtAcc = DB::connection('main')->getPdo()->prepare($queryAcc);
        $stmtAcc->bindParam(':account_id', $accountId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtAcc->execute();
        $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            return response()->json([
                "code"    => "404",
                "message" => "Account not found",
                "data"    => null
            ]);
        }

        $accountBonus = $account['bonus'] ?? 0;
        $response['account_bonus'] = $accountBonus;

        // Get member lock bonus
        $memberLockBonus = $this->lockBonusService->memberLockBonus($customerId, $authBonusType);
        $settings = json_decode($memberLockBonus['setting'] ?? '[]', true);

        // ✅ Ensure $settings is a valid array with expected keys
        $settingCategoryType = $settings['category_type'] ?? '';
        $duration = isset($settings['duration']) ? (int)$settings['duration'] : 0;
        $settingMemberAmount = $settings['member_amount'] ?? 0;
        $settingGetBonus = $settings['get_bonus'] ?? 0;
        $settingTurnover = $settings['turnover'] ?? 0;

        // ✅ Process turnover & progress only if settings are valid
        if ($duration > 0) {
            $startDate = new \DateTime($authLockBonusDate);
            $expireDate = (clone $startDate)->modify("+{$duration} days");
            $now = new \DateTime();

            $memberTurnover = $this->dailyWinloseService->totalTurnover(
                $customerCode,
                $settingCategoryType,
                $authLockBonusDate,
                (int)$duration
            );

            $memberBonusTurnover = ($settingMemberAmount + $settingGetBonus) * $settingTurnover;

            if ($memberBonusTurnover > 0) {
                $progress = ($memberTurnover / $memberBonusTurnover) * 100;
                $progress = min(100, $progress); // cap at 100%
                $progress = number_format($progress, 2);
            }

            // ✅ Fix expiration logic (true = expired)
            $expired = ($now > $expireDate);
        }

        // ✅ Detect if any bonus type is already joined
        $alreadyJoined = !empty($settingCategoryType);

        $settings = array_map(function ($item) use (
            $settingCategoryType, $expired, $progress, $accountBonus, $memberTurnover, $memberBonusTurnover, $alreadyJoined
        ) {
            $isMatched = ($item['category_type'] === $settingCategoryType);
            $joined = $isMatched; // depends on match

            return array_merge($item, [
                'title' => 'Join Bonus',
                'expired' => $isMatched ? $expired : false,
                'joined' => $joined,
                'progress' => $isMatched ? $progress : 0,
                'account_bonus' => $isMatched ? $accountBonus : 0,
                'member_turnover' => $isMatched ? $memberTurnover : 0,
                'member_bonus_turnover' => $isMatched ? $memberBonusTurnover : 0,
                'claimable' => $isMatched ? ($progress >= 100) : false,
                // ✅ Disable join for other bonuses if one is already joined
                'available_join' => $isMatched ? true : !$alreadyJoined,
                // ✅ Optional note
                'note' => (!$isMatched && $alreadyJoined)
                    ? 'Join disabled: another bonus is already joined'
                    : null,
            ]);
        }, $settingList);

        return response()->json([
            "code"    => "200",
            "message" => "Success",
            "data"    => $settings ?? []
        ]);

    }
 
}
