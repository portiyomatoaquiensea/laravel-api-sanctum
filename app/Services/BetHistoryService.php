<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\RedisService;
use Carbon\Carbon;
use PDO;

class BetHistoryService
{
    // Redis
    protected $redisService;
    public function __construct(
        RedisService $redisService,
    )
    {
        $this->redisService = $redisService;
    }

    public function providerList(string $companyCode)
    {
        $redisKey = "vendor/list/{$companyCode}";
        $checkRedisCache = $this->redisService->get($redisKey);
        
        if ($checkRedisCache) {
            $jsonRedisCache = json_decode($checkRedisCache);
            return $jsonRedisCache;
        }

        $queryString = "SELECT name FROM sys.tables WITH (NOLOCK);";

        $pdo = DB::connection('bethistory')->getPdo();
        $stmt = $pdo->prepare($queryString);
        $stmt->execute();

        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Optional: flatten table names into a simple array
        $tableNames = array_column($tables, 'name');

        $querProvider = "
            SELECT 
                name,
                code
            FROM providers WITH (NOLOCK)
            WHERE is_deleted = 0
            AND status = 1
            ORDER BY name ASC;
        ";

        $stmtProvider = DB::connection('main')->getPdo()->prepare($querProvider);
        $stmtProvider->execute();

        $providers = $stmtProvider->fetchAll(PDO::FETCH_ASSOC);

        // ✅ Now map tables to providers (table.name = provider.code)
        $mapped = [];
        if (isset($tables) && isset($providers)) {
            foreach ($tables as $table) {
                $tableName = $table['name'];

                // find matching provider by code
                foreach ($providers as $provider) {
                    if ($provider['code'] === $tableName) {
                        $mapped[] = [
                            'code' => $tableName,
                            'name' => $provider['name'],
                        ];
                        break; // stop searching once matched
                    }
                }
            }
        }
        if ($mapped) {
            $jsonCache = json_encode($mapped);
            // Store the response in Redis for 300 seconds = 5mn
            $this->redisService->set($redisKey, $jsonCache, 300);
        }
        return $mapped;
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

}