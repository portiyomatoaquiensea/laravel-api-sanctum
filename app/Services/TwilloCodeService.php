<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class TwilloCodeService
{
    public function __construct()
    {}

    public function insert(array $data)
    {
        $pdo = DB::connection('main')->getPdo();
        $now = now()->format('Y-m-d H:i:s');

        $query = "
            INSERT INTO twillo_codes (
                phone_number,
                code,
                expired,
                created,
                modified
            )
            VALUES (
                :phone_number,
                :code,
                :expired,
                :created,
                :modified
            )
        ";

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($query);

            $stmt->execute([
                ':phone_number'      => $data['phone_number']      ?? null,
                ':code'    => $data['code']    ?? null,
                ':expired'   => $data['expired']   ?? null,
                ':created'       => $now,
                ':modified'      => $now,
            ]);

            $lastInsertId = $pdo->lastInsertId();
            $pdo->commit();

            return $lastInsertId;
        } catch (PDOException $e) {
            $pdo->rollBack();

            // Optional: log the error for debugging
            Log::error('Insert twillo_codes Failed: ' . $e->getMessage(), [
                'data' => $data
            ]);

            return false;
        }
    }


}