<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class ReferralDetailService
{
    public function __construct()
    {}

    public function insert(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $now = now()->format('Y-m-d H:i:s');

        $query = "
            INSERT INTO referral_details (
                company_id,
                customer_id,
                username,
                id_number,
                dob,
                address,
                phone_number,
                website_url,
                image,
                ip,
                email,
                status,
                created,
                modified
            ) VALUES (
                :company_id,
                :customer_id,
                :username,
                :id_number,
                :dob,
                :address,
                :phone_number,
                :website_url,
                :image,
                :ip,
                :email,
                :status,
                :created,
                :modified
            )
        ";

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($query);

            $stmt->execute([
                ':company_id'   => $data['company_id']   ?? null,
                ':customer_id'  => $data['customer_id']  ?? null,
                ':username'     => $data['username']     ?? null,
                ':id_number'    => $data['id_number']    ?? null,
                ':dob'          => $data['dob']          ?? null, // make sure format is 'Y-m-d'
                ':address'      => $data['address']      ?? null,
                ':phone_number' => $data['phone_number'] ?? null,
                ':website_url'  => $data['website_url']  ?? null,
                ':image'        => $data['image']        ?? null,
                ':ip'           => $data['ip']           ?? null,
                ':email'        => $data['email']        ?? null,
                ':status'       => $data['status']       ?? 0,
                ':created'      => $now,
                ':modified'     => $now,
            ]);

            $lastInsertId = $pdo->lastInsertId();
            $pdo->commit();

            return $lastInsertId;
        } catch (PDOException $e) {
            $pdo->rollBack();

            Log::error('Insert referral_details Failed: ' . $e->getMessage(), [
                'data' => $data
            ]);

            return false;
        }
    }

}