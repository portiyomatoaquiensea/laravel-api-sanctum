<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class X88Service
{
   
    public function __construct(
        
    ) {
        
    }

    public function companyActive(string $companyId, string $provider)
    {
       if (!$companyId || !$provider) {
            return null;
        }

        $query = "
            SELECT TOP 1
            id
            , provider_id
            , provider_code
            , company_id
            , rtp
            , status
            FROM x88_providers WITH (NOLOCK)
            WHERE company_id = :company_id
            AND provider_code = :provider_code
            AND status = 1
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->bindParam(':provider_code', $provider, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?? null;
    }
    
    public function x88Code(string $provider)
    {

        switch ($provider) {
            case config('providerCode.XPP'):
                return 'PRAGMATIC';

            case config('providerCode.XPG'):
                return 'PGSOFT';

            case config('providerCode.XTOPTR'):
                return 'TOPTREND';

            case config('providerCode.XPLSON'):
                return 'PLAYSON';

            case config('providerCode.XBONGO'):
                return 'BOOONGO';

            case config('providerCode.XHASAW'):
                return 'HACKSAW';

            case config('providerCode.XSPB'):
                return 'SPRIBE';

            case config('providerCode.XGENE'):
                return 'GENESIS';

            case config('providerCode.XNAGA'):
                return 'NAGA';

            case config('providerCode.XGMW'):
                return 'GMW';

            case config('providerCode.XEVO'):
                return 'EVOPLAY';

            case config('providerCode.XHABAN'):
                return 'HABANERO';

            default:
                // Optional: code to execute if no cases match
                return '';
        }
    }


}