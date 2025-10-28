<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\RedisService;
use Carbon\Carbon;
use PDO;

class S3Service
{
    // Redis
    protected $redisService;
    public function __construct(
        RedisService $redisService,
    )
    {
        $this->redisService = $redisService;
    }

    public function cdnLink(string $companyId)
    {
        $redisKey = "cdn/link/{$companyId}";
        $checkRedisCache = $this->redisService->get($redisKey);
        
        if ($checkRedisCache) {
            $jsonRedisCache = json_decode($checkRedisCache);
            return $jsonRedisCache;
        }

        $query = "
            SELECT 
                company_id,
                cdn_url
            FROM aws_permissions WITH (NOLOCK)
            WHERE company_id = :company_id;";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $jsonCache = json_encode($result);
            // Store the response in Redis for 300 seconds = 5mn
            $this->redisService->set($redisKey, $jsonCache, 300);
        }

        return (object)$result;
    }


}