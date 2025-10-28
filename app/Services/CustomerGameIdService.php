<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class CustomerGameIdService
{
   
    public function __construct(
        
    ) {
        
    }

    public function password(string $customerId, string $provider)
    {
        
        if (!$customerId || !$gameId) {
            return null;
        }

        $query = "SELECT TOP 1
            password
            FROM customer_gameids WITH (NOLOCK) 
            WHERE customer_id = :customer_id
            AND provider_id = :provider_id";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':provider_id', $provider, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?? null;
    }

}