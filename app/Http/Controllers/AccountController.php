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

use App\Services\Account2Service;
use App\Services\TransactionService;
use App\Services\NotificationService;
use App\Services\BoPushService;
use Carbon\Carbon;
use PDO;

class AccountController extends Controller
{

    // Service
    protected $account2Service;
    protected $notificationService;
    protected $boPushService;

    public function __construct(

        Account2Service $account2Service,
        NotificationService $notificationService,
        BoPushService $boPushService,
    ) {

        $this->account2Service = $account2Service;
        $this->notificationService = $notificationService;
        $this->boPushService = $boPushService;
    }

    public function memberAccount(Request $request, SessionService $sessionService)
    {   
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $companyId = $user->getCompanyId();
        $customerId = $user->getId();
       
        $queryString = "
            SELECT 
                acc2.id as to_account_id,
                acc2.bank_id as account_bank_id,
                acc2.customer_id,
                acc2.company_id,
                acc2.account_name,
                acc2.account_number,
                acc2.is_primary,
                acc2.deleted,
                b.id AS bank_id,
                b.name AS bank_name
            FROM account2s acc2 WITH (NOLOCK)
            INNER JOIN banks b ON acc2.bank_id = b.id
            WHERE acc2.customer_id = :customer_id
            AND acc2.company_id = :company_id
            AND acc2.deleted = 0
            AND acc2.status = 1
            AND b.status = 1
            AND b.deleted = 0
            ORDER BY acc2.is_primary DESC
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($queryString);

        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => $result
        ]);
    }

    public function operatorAccount(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $companyId = $user->getCompanyId();
        $customerId = $user->getId();
        $depositCount = 5; // Example deposit count

        // Step 1: Get all bank IDs linked to customer
        $accountBankIds = DB::connection('main')
            ->table(DB::raw('account2s WITH (NOLOCK)'))
            ->where('customer_id', $customerId)
            ->pluck('bank_id');

        // Step 2: Get priority company accounts
        $priorityAccountIds = DB::connection('main')
            ->table(DB::raw('company_accounts WITH (NOLOCK)'))
            ->where('company_id', $companyId)
            ->whereIn('bank_id', $accountBankIds)
            ->where('deleted', 0)
            ->where('is_primary', 1)
            ->pluck('id');

        // Step 3: Get customer deposit company accounts
        $customerDepositAccountIds = DB::connection('main')
            ->table(DB::raw('customer_deposits AS cd WITH (NOLOCK)'))
            ->join(DB::raw('company_accounts AS ca WITH (NOLOCK)'), 'cd.company_account_id', '=', 'ca.id')
            ->where('cd.customer_id', $customerId)
            ->where('cd.company_id', $companyId)
            ->where('ca.deleted', 0)
            ->pluck('company_account_id');

        // Step 4: Always display company accounts
        $alwaysDisplayAccountIds = DB::connection('main')
            ->table(DB::raw('company_accounts WITH (NOLOCK)'))
            ->where('company_id', $companyId)
            ->where('deleted', 0)
            ->where('alway_display', 1)
            ->pluck('id');

        // Step 5: Operator range company accounts
        $operatorRangeAccountIds = DB::connection('main')
            ->table(DB::raw('operator_bank_group_settings AS obgs WITH (NOLOCK)'))
            ->join(DB::raw('company_accounts AS ca WITH (NOLOCK)'), 'obgs.company_account_id', '=', 'ca.id')
            ->where('obgs.company_id', $companyId)
            ->where('obgs.min', '<=', $depositCount)
            ->where('obgs.max', '>=', $depositCount)
            ->pluck('company_account_id');

        // Step 6: Decide which account IDs to use
        if ($operatorRangeAccountIds->isNotEmpty()) {
            $finalAccountIds = $operatorRangeAccountIds;
        } elseif ($customerDepositAccountIds->isNotEmpty()) {
            $finalAccountIds = $customerDepositAccountIds->merge($alwaysDisplayAccountIds)->unique();
        } else {
            $finalAccountIds = $priorityAccountIds->merge($alwaysDisplayAccountIds)->unique();
        }

        // Step 7: Get final accounts with bank info
        $accounts = DB::connection('main')
            ->table(DB::raw('company_accounts AS ca WITH (NOLOCK)'))
            ->join(DB::raw('banks AS b WITH (NOLOCK)'), 'b.id', '=', 'ca.bank_id')
            ->leftJoin(DB::raw('company_banks AS cb WITH (NOLOCK)'), function ($join) {
                $join->on('cb.bank_id', '=', 'ca.bank_id')
                    ->on('cb.company_id', '=', 'ca.company_id')
                    ->where('cb.status', 1);
            })
            ->whereIn('ca.id', $finalAccountIds)
            ->where('b.status', 1)
            ->where('b.deleted', 0)
            ->select(
                'ca.id',
                'ca.name',
                'ca.number',
                'b.id as bank_id',
                'b.name as bank_name',
                'b.wallet_type'
            )
            ->distinct()
            ->orderBy('b.wallet_type')
            ->orderBy('b.name')
            ->orderBy('ca.name')
            ->get();

        $bankTypeMap = [
            1 => 'Bank',
            2 => 'EWallet',
            3 => 'Pulsa',
        ];

        // Group accounts by wallet_type
        $groupedBanks = $accounts->groupBy(function ($account) use ($bankTypeMap) {
            return $bankTypeMap[$account->wallet_type] ?? 'Unknown';
        });

        // Send $groupedBanks to frontend
        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => $groupedBanks
        ]);
    }

    public function createAccount(Request $request, SessionService $sessionService)
    {
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }
        $companyId = $user->getCompanyId();
        $companyCode = $user->getCompanyCode();
        $companyName = $user->getCompanyName();
        $customerId = $user->getId();
        $username = $user->getUsername();
        $groupId = $user->getGroupId();

        // $companyId = "e615a47c-a210-4a13-b3a7-edaa71ec645f";
        // $companyName = "KASIRJUDI";
        // $customerId = "D13750EB-0F43-4991-A44F-B7DA770B6DA0";
        // $accountId = "4D97EBB9-092E-4E19-BD97-F5E69149CCA2";
        // $username = "narakasir2";
        // $groupId = "3";

        $validator = Validator::make($request->all(), [
            'bank_id'      => 'required|string',
            'bank_name'   => 'required|string',
            'account_name'   => 'required|string',
            'account_number'   => 'required|string',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ]);
        }

        $bankId = $request->input('bank_id');
        $bankName = $request->input('bank_name');
        $accountName = $request->input('account_name');
        $accountNumber = $request->input('account_number');
        $query = "
            SELECT TOP 1 1
            FROM account2s WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id
            AND bank_id = :bank_id
            AND account_number = :account_number
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->bindParam(':bank_id', $bankId, PDO::PARAM_STR);
        $stmt->bindParam(':account_number', $accountNumber, PDO::PARAM_STR);
        $stmt->execute();
        $exists = $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        if ($exists) {
            return response()->json([
                "code"    => "422",
                "message" => "This account already Taken",
                "data"    => null
            ]);
        }

        $account2Data = [
            'company_id' => $companyId,
            'bank_id' => $bankId,
            'customer_id' => $customerId,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'is_primary' => 0,
            'status' => 0,
            'deleted' => 0,
        ];
        
        $newAccount2Id = $this->account2Service->create($account2Data);
        if (!$newAccount2Id) {
            return response()->json([
                "code"    => "500",
                "message" => "Failed to create account",
                "data"    => null
            ]);
        }
        $account2Data['id'] = $newAccount2Id;
        $account2Data['company_name'] = $companyName;
        $account2Data['customer_name'] = $username;
        $account2Data['bank_name'] = $bankName;
        $account2Data['group_id'] = $groupId;
        $newAccount2Obj = (object) $account2Data;

        $boPushService = $this->boPushService->newMemberBank($newAccount2Obj);
        $notificationService = $this->notificationService->update($newAccount2Obj, 'member_bank');
        
        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => $newAccount2Obj
        ]);
    }

    public function memberAccountList(Request $request, SessionService $sessionService)
    {
        // 🔒 Uncomment these for real user session
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $companyId = $user->getCompanyId();
        $customerId = $user->getId();

        // $companyId = "e615a47c-a210-4a13-b3a7-edaa71ec645f";
        // $customerId = "D13750EB-0F43-4991-A44F-B7DA770B6DA0";
    
        $page  = max((int) $request->input('page', 1), 1);
        $limit = max((int) $request->input('limit', 10), 1);
        $offset = ($page - 1) * $limit;

        // Query Account
        $queryAccount = "
            SELECT 
                account.id AS account_id, 
                account.customer_id,
                account.company_id,
                account.bank_id AS account_bank_id,
                account.account_name,
                account.account_number, 
                account.is_primary,
                account.status,
                account.deleted,
                bank.name AS bank_name
            FROM account2s AS account WITH (NOLOCK)
            LEFT JOIN banks AS bank WITH (NOLOCK) ON account.bank_id = bank.id
            WHERE account.customer_id = :customer_id
            AND account.company_id = :company_id
            AND account.deleted = 0
            ORDER BY account.is_primary DESC
            OFFSET :offset ROWS
            FETCH NEXT :limit ROWS ONLY
        ";

        $pdo = DB::connection('main')->getPdo();
        $stmt = $pdo->prepare($queryAccount);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ✅ Corrected total count query
        $countQuery = "
            SELECT COUNT(*) AS total
            FROM account2s WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id
            AND deleted = 0
        ";

        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $countStmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();


        if (empty($data)) {
            return response()->json([
                "code"    => "404",
                "message" => "No Account found",
                "data"    => null
            ]);
        }

        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => [
                "current_page" => $page,
                "per_page"     => $limit,
                "total"        => $total,
                "total_pages"  => ceil($total / $limit),
                "records"      => $data
            ]
        ]);
    }
}
