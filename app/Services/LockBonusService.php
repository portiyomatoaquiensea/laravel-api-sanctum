<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\DailyWinloseService;
use Carbon\Carbon;
use PDO;

class LockBonusService
{
    protected $dailyWinloseService;
    public function __construct(
        DailyWinloseService $dailyWinloseService
    ) {
        $this->dailyWinloseService = $dailyWinloseService;
    }

    public function categories()
    {
        return [
            'LOCK_BONUS_CASINO' => 'casino',
            'LOCK_BONUS_SLOT' => 'slot',
            'LOCK_BONUS_POKER' => 'poker',
            'LOCK_BONUS_SPORTBOOK' => 'sportbook',
            'LOCK_BONUS_LOTTERY' => 'lottery',
            'LOCK_BONUS_FISHING' => 'fishing',
            'LOCK_BONUS_COCKFIGHT' => 'cockfight',
            'LOCK_BONUS_HOTGAME' => 'hotgame',
        ];
    }
    
    public function log(string $customerId, string $type)
    {
        if (!$customerId || !$type) {
            return null;
        }


        $query = "
            SELECT TOP 1
                setting
            FROM lock_bonus_logs WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND bonus_type = :bonus_type";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':bonus_type', $type, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?? null;
    }

    public function setting(string $companyId, string $type)
    {
        if (!$companyId || !$type) {
            return null;
        }


        $query = "
            SELECT TOP 1
                bonus
                , join_amount
                , payout_amount
                , turnover
                , duration
                , status
            FROM lock_bonus_settings WITH (NOLOCK)
            WHERE company_id = :company_id
            AND category_type = :category_type
            AND status = 1";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->bindParam(':category_type', $type, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?? null;
    }

    public function saveLockBonusLog(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $date = Carbon::now()->format('Y-m-d H:i:s');

        $query = "
            INSERT INTO lock_bonus_logs (
                customer_id,
                company_id,
                user_id,
                begin_bonus,
                end_bonus,
                join_bonus_amount,
                given_bonus_amount,
                bonus_type,
                setting,
                action,
                created,
                modified
            ) VALUES (
                :customer_id,
                :company_id,
                :user_id,
                :begin_bonus,
                :end_bonus,
                :join_bonus_amount,
                :given_bonus_amount,
                :bonus_type,
                :setting,
                :action,
                :created,
                :modified
            )
        ";

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($query);

            $stmt->execute([
                ':customer_id'        => $data['customer_id']        ?? null,
                ':company_id'         => $data['company_id']         ?? null,
                ':user_id'            => $data['user_id']            ?? null,
                ':begin_bonus'        => $data['begin_bonus']        ?? 0,
                ':end_bonus'          => $data['end_bonus']          ?? 0,
                ':join_bonus_amount'  => $data['join_bonus_amount']  ?? 0,
                ':given_bonus_amount' => $data['given_bonus_amount'] ?? 0,
                ':bonus_type'         => $data['bonus_type']         ?? '',
                ':setting'            => $data['setting']            ?? '',
                ':action'             => $data['action']             ?? '',
                ':created'            => $date,
                ':modified'           => $date,
            ]);

            $lastInsertId = $pdo->lastInsertId();
            $pdo->commit();

            return $lastInsertId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function saveLockBonusReport(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $date = Carbon::now()->format('Y-m-d H:i:s');

        $query = "
            INSERT INTO lock_bonus_reports (
                group_id,
                company_id,
                company_name,
                customer_id,
                customer_username,
                customer_code,
                bonus_type,
                join_amount,
                turnover,
                given_amount,
                claim_amount,
                first_deposit,
                join_date,
                claim_type,
                created,
                modified
            ) VALUES (
                :group_id,
                :company_id,
                :company_name,
                :customer_id,
                :customer_username,
                :customer_code,
                :bonus_type,
                :join_amount,
                :turnover,
                :given_amount,
                :claim_amount,
                :first_deposit,
                :join_date,
                :claim_type,
                :created,
                :modified
            )
        ";

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($query);

            $stmt->execute([
                ':group_id'         => $data['group_id']         ?? 0,
                ':company_id'       => $data['company_id']       ?? '',
                ':company_name'     => $data['company_name']     ?? '',
                ':customer_id'      => $data['customer_id']      ?? '',
                ':customer_username'=> $data['customer_username']?? '',
                ':customer_code'    => $data['customer_code']    ?? '',
                ':bonus_type'       => $data['bonus_type']       ?? '',
                ':join_amount'      => $data['join_amount']      ?? 0,
                ':turnover'         => $data['turnover']         ?? 0,
                ':given_amount'     => $data['given_amount']     ?? 0,
                ':claim_amount'     => $data['claim_amount']     ?? 0,
                ':first_deposit'    => $data['first_deposit']    ?? 0,
                ':join_date'        => $data['join_date']        ?? date('Y-m-d'),
                ':claim_type'       => $data['claim_type']       ?? null,
                ':created'          => $date,
                ':modified'         => $date,
            ]);

            $lastInsertId = $pdo->lastInsertId();
            $pdo->commit();

            return $lastInsertId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function settingList(string $companyId)
    {
        if (!$companyId) {
            return null;
        }

        $query = "
            SELECT 
                id
                , category_type
                , bonus
                , join_amount as min_amount
                , payout_amount as max_amount
                , turnover
                , duration
                , status
            FROM lock_bonus_settings WITH (NOLOCK)
            WHERE company_id = :company_id
            AND status = 1
            ORDER BY category_type ASC";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result ?? null;
    }

    public function memberLockBonus(string $customerId, string $type = null)
    {
        if (!$customerId || !$type) {
            return null;
        }

        $query = "
            SELECT TOP 1
                setting
            FROM lock_bonus_logs WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND bonus_type = :bonus_type";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':bonus_type', $type, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?? null;
    }

    public function checkLockBonus(array $logBonusData): array 
    {
        $customerCode = $logBonusData['customer_code'];
        $joinedBonus = $logBonusData['joined_bonus'];
        $lockBonusAction = $logBonusData['lock_bonus_action'];
        $authBonusType = $logBonusData['auth_bonus_type'];
        $lockBonusDate = $logBonusData['lock_bonus_date'];
        $callType = $logBonusData['call_type'];
        $lockBonusDuration = $logBonusData['lock_bonus_duration'];
        $lockBonusTurnover = $logBonusData['lock_bonus_turnover'];
        $lockBonusMemberAmount = $logBonusData['lock_bonus_member_amount'];
        $lockBonusGetBonus = $logBonusData['lock_bonus_get_bonus'];

        // Load config values once
        $arrayBonusStatus = [
            config('constraint.LOCK_BONUS_LOG_APPROVE'),
            config('constraint.LOCK_BONUS_LOG_REJECT'),
            config('constraint.LOCK_BONUS_LOG_RELEASE'),
            config('constraint.LOCK_BONUS_LOG_UNLOCK')
        ];

        // 1️⃣ If user hasn't joined any bonus
        if ($joinedBonus === "NO") {
            return [
                'status' => true,
                'message' => 'User has not joined any bonus — operation allowed.'
            ];
        }

        // 2️⃣ If joined but action is not valid
        if (in_array($lockBonusAction, $arrayBonusStatus)) {
            return [
                'status' => true,
                'message' => 'Lock bonus is completed — operation allowed.'
            ];
        }
        
        // 3️⃣ If joined but bonus type mismatch
        if ($authBonusType !== $callType) {
            return [
                'status' => false,
                'message' => 'Bonus type mismatch — authorization failed.'
            ];
        }

        // 4️⃣ Check if lock bonus expired
        if ($lockBonusDate && $lockBonusDuration > 0) {
            $expiredDate = date('Y-m-d H:i:s', strtotime($lockBonusDate . " +{$lockBonusDuration} days"));
            $now = date('Y-m-d H:i:s');
            $totalTurnover = $this->dailyWinloseService->totalTurnover(
                $customerCode,
                $authBonusType,
                $lockBonusDate,
                $lockBonusDuration
            );

            if (($now > $expiredDate) && $totalTurnover == 0) {
                return [
                    'status' => false,
                    'message' => "Lock bonus has expired expired on {$expiredDate} and empty turnover, please contact customer support.",
                ];
            }
            
            if (($now > $expiredDate) && $totalTurnover > 0) {
                
                $calculatedBonus = ($lockBonusMemberAmount + $lockBonusGetBonus) * $lockBonusTurnover;
                if ($totalTurnover >= $calculatedBonus) {
                    return [
                        'status' => false,
                        'message' => "Lock bonus has expired expired on {$expiredDate} and turnover was not enough, please contact customer support.",
                    ];
                }
            }
        }

        // 4️⃣ If all checks pass
        return [
            'status' => true,
            'message' => 'Bonus check passed — operation allowed.'
        ];
    }


}