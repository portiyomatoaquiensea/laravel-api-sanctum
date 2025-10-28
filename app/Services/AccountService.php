<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class AccountService
{
    public function __construct()
    {}

    public function create(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $date = Carbon::now()->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO accounts (
                id,
                user_id,
                bank_id,
                company_id,
                customer_id,
                name,
                number,
                house,
                main,
                bonus,
                balance,
                deleted,
                created,
                modified,
                bonus_type,
                is_bonus,
                join_amount,
                regiser_date
            )
            OUTPUT inserted.id
            VALUES (
                NEWID(),
                :user_id,
                :bank_id,
                :company_id,
                :customer_id,
                :name,
                :number,
                :house,
                :main,
                :bonus,
                :balance,
                :deleted,
                :created,
                :modified,
                :bonus_type,
                :is_bonus,
                :join_amount,
                :regiser_date)");

        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':bank_id' => $data['bank_id'] ?? null,
            ':company_id' => $data['company_id'] ?? null,
            ':customer_id' => $data['customer_id'] ?? null,
            ':name' => $data['name'] ?? null,
            ':number' => $data['number'] ?? null,
            ':house' => $data['house'] ?? 0,
            ':main' => $data['main'] ?? 0,
            ':bonus' => $data['bonus'] ?? 0.00,
            ':balance' => $data['balance'] ?? 0.00,
            ':deleted' => $data['deleted'] ?? 0,
            ':created' => $date,
            ':modified' => $date,
            ':bonus_type' => $data['bonus_type'] ?? null,
            ':is_bonus' => $data['is_bonus'] ?? 0,
            ':join_amount' => $data['join_amount'] ?? 0.00,
            ':regiser_date' => $data['regiser_date'] ?? $date,  
        ]);
        // Fetch the DB-generated UUID
        $newId = $stmt->fetchColumn();
        return $newId;
    }

    public function updateBalance(string $accountId, float $lastBalance)
    {
        try {
            $date = Carbon::now()->format('Y-m-d H:i:s');
            $affected = DB::connection('main')->update(
                "UPDATE accounts 
                SET balance = :last_balance,
                modified = :modified 
                WHERE id = :account_id",
                [
                    'last_balance' => $lastBalance,
                    'modified'   => $date,
                    'account_id'   => $accountId,
                ]
            );
            if ($affected > 0) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function updateJoinBonus(array $data)
    {
        if (!$data) {
            return false;
        }
        $accountId = $data['account_id'];
        $lastBonus = $data['last_bonus'];
        $lastBalance = $data['last_balance'];
        $amount = $data['amount'];
        $bonusType = $data['bonus_type'];

        try {
            $date = Carbon::now()->format('Y-m-d H:i:s');
            $affected = DB::connection('main')->update(
                "UPDATE accounts 
                SET 
                bonus = :last_bonus,
                balance = :last_balance,
                bonus_type = :bonus_type,
                join_amount = :amount,
                is_bonus = :is_bonus,
                modified = :modified 
                WHERE id = :account_id",
                [
                    'last_bonus' => $lastBonus,
                    'last_balance' => $lastBalance,
                    'bonus_type' => $bonusType,
                    'amount' => $amount,
                    'is_bonus' => 1,
                    'modified'   => $date,
                    'account_id'   => $accountId,
                ]
            );
            if ($affected > 0) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
    

}