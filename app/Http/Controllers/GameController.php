<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\OpenUrl;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use Carbon\Carbon;

class GameController extends Controller
{
    public function slot(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $gameCode   = $request->input('game_code');

        if (empty($companyKey) || empty($gameCode)) {
            return response()->json([
                "code"    => "400",
                "message" => "Missing required parameters",
                "data"    => null
            ], 400);
        }

        $queryString = "SELECT id
                ,provider
                ,game_code
                ,game_id
                ,name
                ,rtp
                ,type
                ,tag_name
                ,url
                ,noted
                ,sort
                ,show
                ,maintenance
                ,deleted
                ,brand_name
            FROM games WITH (NOLOCK) 
            WHERE game_code = :game_code
            AND show = 1
            AND deleted = 0
            AND maintenance = 0
            ORDER BY sort ASC";

        $stmt = DB::connection('main')->getPdo()->prepare($queryString);
        $stmt->bindParam(':game_code', $gameCode, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$result) {
            return response()->json([
                "code"    => "404",
                "message" => "Page not found",
                "data"    => null
            ], 404);
        }
        
        return response()->json([
            "code"    => "200",
            "message" => "Success",
            "data"    => $result
        ]);
    }
}
