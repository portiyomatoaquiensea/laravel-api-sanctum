<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class TransferService
{
    public function __construct()
    {}

    public function insert(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $date = Carbon::now()->format('Y-m-d H:i:s');
        $query = "
            INSERT INTO transfers (
                user_id,
                customer_id,
                account_id,
                to_account_id,
                amount,
                bonus,
                type,
                payment_type,
                pga_fee_amount,
                status,
                ip_address,
                description,
                transfer_date,
                created,
                modified,
                operator_bank,
                customer_bank,
                company_id,
                operator_name,
                customer_name,
                customer_code,
                group_id,
                company_account_fee,
                customer_point,
                customer_bank_name,
                account_balance,
                image,
                f_dp,
                f_wd
            ) VALUES (
                :user_id,
                :customer_id,
                :account_id,
                :to_account_id,
                :amount,
                :bonus,
                :type,
                :payment_type,
                :pga_fee_amount,
                :status,
                :ip_address,
                :description,
                :transfer_date,
                :created,
                :modified,
                :operator_bank,
                :customer_bank,
                :company_id,
                :operator_name,
                :customer_name,
                :customer_code,
                :group_id,
                :company_account_fee,
                :customer_point,
                :customer_bank_name,
                :account_balance,
                :image,
                :f_dp,
                :f_wd
            )
        ";

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($query);

            $stmt->execute([
                ':user_id'             => $data['user_id']             ?? null,
                ':customer_id'         => $data['customer_id'], // required
                ':account_id'          => $data['account_id'],  // required
                ':to_account_id'       => $data['to_account_id']       ?? null,
                ':amount'              => $data['amount']              ?? 0,
                ':bonus'               => $data['bonus']               ?? 0,
                ':type'                => $data['type']                ?? 0,
                ':payment_type'        => $data['payment_type']        ?? '',
                ':pga_fee_amount'      => $data['pga_fee_amount']      ?? 0,
                ':status'              => $data['status']              ?? 0,
                ':ip_address'          => $data['ip_address']          ?? '0.0.0.0',
                ':description'         => $data['description']         ?? '',
                ':transfer_date'       => $data['transfer_date']       ?? date('Y-m-d'),
                ':created'             => $date,
                ':modified'            => $date,
                ':operator_bank'       => $data['operator_bank']       ?? '',
                ':customer_bank'       => $data['customer_bank']       ?? '',
                ':company_id'          => $data['company_id']          ?? null,
                ':operator_name'       => $data['operator_name']       ?? '',
                ':customer_name'       => $data['customer_name']       ?? '',
                ':customer_code'       => $data['customer_code']       ?? '',
                ':group_id'            => $data['group_id']            ?? 0,
                ':company_account_fee' => $data['company_account_fee'] ?? 0,
                ':customer_point'      => $data['customer_point']      ?? 0,
                ':customer_bank_name'  => $data['customer_bank_name']  ?? '',
                ':account_balance'     => $data['account_balance']     ?? 0,
                ':image'               => $data['image']               ?? '',
                ':f_dp'                => $data['f_dp']                ?? 0,
                ':f_wd'                => $data['f_wd']                ?? 0,
            ]);

            $lastInsertId = $pdo->lastInsertId();
            $pdo->commit();

            return $lastInsertId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            return false;
        }

    }
}