<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class GameService
{
   
    public function __construct(
        
    ) {
        
    }

    public function active(string $provider, string $gameId)
    {
        
        if (!$provider || !$gameId) {
            return null;
        }

        $query = "
            SELECT TOP 1
            id
            , provider
            , game_code
            , game_id
            FROM games WITH (NOLOCK)
            WHERE provider = :provider
            AND game_id = :game_id
            AND show = 1
            AND deleted = 0
            AND maintenance = 0
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':provider', $provider, PDO::PARAM_STR);
        $stmt->bindParam(':game_id', $gameId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?? null;
    }

}