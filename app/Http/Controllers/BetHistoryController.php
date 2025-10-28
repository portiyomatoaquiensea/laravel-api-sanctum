<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

use App\Services\SessionService; 
use App\Services\BetHistoryService;

use Carbon\Carbon;
use PDO;

class BetHistoryController extends Controller
{

    protected $betHistoryService;
    public function __construct(
        BetHistoryService $betHistoryService,
    ) {
        $this->betHistoryService = $betHistoryService;
    }

    public function vendorList(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $companyCode = $user->getCompanyCode();
        $providerList = $this->betHistoryService->providerList($companyCode);
      
        return response()->json([
            "code" => "200",
            "message" => "Successfully",
            "data" => $providerList
        ]);
    
    }

    public function memberBetHistory(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $customerCode = $user->getCustomerCode();
        $companyCode  = $user->getCompanyCode();

        // ✅ Validation (supports datetime)
        $validator = Validator::make($request->all(), [
            'page'       => 'required|integer|min:1',
            'limit'      => 'required|integer|min:1|max:100',
            'provider'   => 'required|string',
            'from_date'  => 'required|date_format:Y-m-d H:i:s',
            'end_date'   => 'required|date_format:Y-m-d H:i:s|after_or_equal:from_date',
            'status'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "code"    => "422",
                "message" => "Missing or invalid parameters",
                "data"    => null
            ]);
        }

        // 📄 Pagination
        $page   = max((int) $request->input('page', 1), 1);
        $limit  = max((int) $request->input('limit', 10), 1);
        $offset = ($page - 1) * $limit;

        $status   = $request->input('status');
        $table    = $request->input('provider');
        $fromDate = $request->input('from_date');
        $endDate  = $request->input('end_date');

        // 🕒 Check date range (max 7 days)
        $from = new \DateTime($fromDate);
        $to   = new \DateTime($endDate);
        $diff = $from->diff($to)->days;
        if ($diff > 7) {
            return response()->json([
                "code"    => "400",
                "message" => "Date range cannot exceed 7 days",
                "data"    => null
            ]);
        }

        // ✅ Validate provider
        $providerList = $this->betHistoryService->providerList($companyCode);
        if (empty($providerList)) {
            return response()->json([
                "code"    => "400",
                "message" => "Provider list not found",
                "data"    => null
            ]);
        }

        $codes = array_column($providerList, 'code');
        if (!in_array($table, $codes, true)) {
            return response()->json([
                "code"    => "400",
                "message" => "Invalid provider code",
                "data"    => null
            ]);
        }
        $matchedProvider = collect($providerList)
            ->firstWhere('code', $table);

        // ✅ Connect to DB
        $pdo = DB::connection('bethistory')->getPdo();

        // ✅ Build main query
        $query = "
            SELECT 
                id,
                refno,
                refno2,
                code,
                room,
                game_id,
                game,
                game_table,
                order_time,
                settle_time,
                game_start_time,
                bet_detail,
                bet,
                turnover,
                win_lose,
                status,
                type,
                provider
            FROM {$table} WITH (NOLOCK)
            WHERE code = :customer_code
            AND provider = :provider_code
        ";

        if ($fromDate && $endDate) {
            $query .= " AND settle_time BETWEEN :from_date AND :end_date";
        }

        if ($status === 'running') {
            $query .= " AND status IN ('running', 'runing', 'waiting')";
        }

        if ($status === 'settled') {
            $query .= " AND status NOT IN ('running', 'runing', 'waiting')";
        }

        $query .= "
            ORDER BY settle_time DESC
            OFFSET :offset ROWS
            FETCH NEXT :limit ROWS ONLY
        ";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':customer_code', $customerCode, PDO::PARAM_STR);
        $stmt->bindParam(':provider_code', $table, PDO::PARAM_STR);
        if ($fromDate && $endDate) {
            $stmt->bindParam(':from_date', $fromDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            return response()->json([
                "code"    => "404",
                "message" => "No records found",
                "data"    => null
            ]);
        }

        // ✅ Fixed total count query (with datetime)
        $countQuery = "
            SELECT COUNT(*) AS total
            FROM {$table} WITH (NOLOCK)
            WHERE code = :customer_code
            AND provider = :provider_code
        ";
        if ($fromDate && $endDate) {
            $countQuery .= " AND settle_time BETWEEN :from_date AND :end_date";
        }
        if ($status === 'running') {
            $countQuery .= " AND status IN ('running', 'runing', 'waiting')";
        }

        if ($status === 'settled') {
            $query .= " AND status NOT IN ('running', 'runing', 'waiting')";
        }
        
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindParam(':customer_code', $customerCode, PDO::PARAM_STR);
        $countStmt->bindParam(':provider_code', $table, PDO::PARAM_STR);
        if ($fromDate && $endDate) {
            $countStmt->bindParam(':from_date', $fromDate);
            $countStmt->bindParam(':end_date', $endDate);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        // ✅ Final Response
        return response()->json([
            "code"    => "200",
            "message" => "Success",
            "data"    => [
                "provider" => $matchedProvider,
                "current_page" => $page,
                "per_page"     => $limit,
                "total"        => $total,
                "total_pages"  => ceil($total / $limit),
                "records"      => $data
            ]
        ]);
    }
 
}
