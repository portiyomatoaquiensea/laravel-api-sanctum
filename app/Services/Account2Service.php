<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class Account2Service
{
    public function __construct()
    {}

    public function create(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $date = Carbon::now()->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO account2s (
                id,
                customer_id,
                company_id,
                bank_id,
                account_name,
                account_number,
                is_primary,
                deleted,
                status,
                created,
                modified
            )
            OUTPUT inserted.id
            VALUES (
                NEWID(),
                :customer_id,
                :company_id,
                :bank_id,
                :account_name,
                :account_number,
                :is_primary,
                :deleted,
                :status,
                :created,
                :modified)");

        $stmt->execute([
            ':customer_id' => $data['customer_id'] ?? null,
            ':company_id' => $data['company_id'], // required
            ':bank_id' => $data['bank_id'],  // required
            ':account_name' => $data['account_name'] ?? null,
            ':account_number' => $data['account_number'] ?? 0,
            ':is_primary' => $data['is_primary'] ?? 0,
            ':deleted' => $data['deleted'] ?? 0,
            ':status' => $data['status'] ?? 0,
            ':created' => $date,
            ':modified' => $date, 
        ]);
        // Fetch the DB-generated UUID
        $newId = $stmt->fetchColumn();
        return $newId;
    }
    
}