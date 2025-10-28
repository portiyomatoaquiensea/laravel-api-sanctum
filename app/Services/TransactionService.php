<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class TransactionService
{
    public function __construct()
    {}

    public function insert(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $now = now()->format('Y-m-d H:i:s');

        $query = "
            INSERT INTO transactions (
                provider,
                account_id,
                transfer_id,
                ip_address,
                begin_balance,
                amount,
                end_balance,
                description,
                type,
                wallet_type,
                status,
                created,
                modified
            )
            VALUES (
                :provider,
                :account_id,
                :transfer_id,
                :ip_address,
                :begin_balance,
                :amount,
                :end_balance,
                :description,
                :type,
                :wallet_type,
                :status,
                :created,
                :modified
            )
        ";

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($query);

            $stmt->execute([
                ':provider'      => $data['provider']      ?? null,
                ':account_id'    => $data['account_id']    ?? null,
                ':transfer_id'   => $data['transfer_id']   ?? null,
                ':ip_address'    => $data['ip_address']    ?? request()->ip(),
                ':begin_balance' => $data['begin_balance'] ?? 0,
                ':amount'        => $data['amount']        ?? 0,
                ':end_balance'   => $data['end_balance']   ?? 0,
                ':description'   => $data['description']   ?? '',
                ':type'          => $data['type']          ?? '',
                ':wallet_type'   => $data['wallet_type']   ?? '',
                ':status'        => $data['status']        ?? 0,
                ':created'       => $now,
                ':modified'      => $now,
            ]);

            $lastInsertId = $pdo->lastInsertId();
            $pdo->commit();

            return $lastInsertId;
        } catch (PDOException $e) {
            $pdo->rollBack();

            // Optional: log the error for debugging
            Log::error('Insert Transaction Failed: ' . $e->getMessage(), [
                'data' => $data
            ]);

            return false;
        }
    }


}