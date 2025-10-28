<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

use App\Services\SessionService; // Session Service
use App\Services\RedisService;

use App\Services\AccountService;
use App\Services\TransferService;
use App\Services\TransactionService;
use App\Services\NotificationService;
use App\Services\BoPushService;
use Carbon\Carbon;
use DateTime;
use PDO;

class WithdrawController extends Controller
{

    // Redis
    protected $redisService;
    // Service
  
    protected $accountService;
    protected $transferService;
    protected $transactionService;
    protected $notificationService;
    protected $boPushService;

    public function __construct(
        RedisService $redisService,

        AccountService $accountService,
        TransferService $transferService,
        TransactionService $transactionService,
        NotificationService $notificationService,
        BoPushService $boPushService,
    ) {
        $this->redisService = $redisService;

        $this->accountService = $accountService;
        $this->transferService = $transferService;
        $this->transactionService = $transactionService;
        $this->notificationService = $notificationService;
        $this->boPushService = $boPushService;
    }

    public function memberWithdrawHistory(Request $request, SessionService $sessionService)
    {
        // 🔒 Auth check
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                "code"    => "403",
                "message" => "Please login first",
                "data"    => null
            ]);
        }

        $companyId  = $user->getCompanyId();
        $customerId = $user->getId();

        // ✅ Validate request
        $validator = Validator::make($request->all(), [
            'page'       => 'required|integer|min:1',
            'limit'      => 'required|integer|min:1|max:100',
            'from_date'  => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
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

        // 📅 Date range validation
        $fromDate = $request->input('from_date');
        $endDate  = $request->input('end_date');

        if ($fromDate && $endDate) {
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
        }

        $typeWithdraw = config('constraint.TYPE_WITHDRAW');
        $pdo = DB::connection('main')->getPdo();

        // 🧾 Build query dynamically
        $queryTransfer = "
            SELECT 
                id, 
                company_id,
                customer_id,
                amount,
                operator_bank,
                customer_bank, 
                type, 
                payment_type,
                status,
                transfer_date,
                modified
            FROM transfers WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id
            AND type = :type
        ";

        if ($fromDate && $endDate) {
            $queryTransfer .= " AND transfer_date BETWEEN :from_date AND :end_date";
        }

        $queryTransfer .= "
            ORDER BY modified DESC
            OFFSET :offset ROWS
            FETCH NEXT :limit ROWS ONLY
        ";

        $stmt = $pdo->prepare($queryTransfer);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmt->bindParam(':type', $typeWithdraw, PDO::PARAM_STR);
        if ($fromDate && $endDate) {
            $stmt->bindParam(':from_date', $fromDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 🔢 Total count
        $countQuery = "
            SELECT COUNT(*) AS total
            FROM transfers WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id
            AND type = :type
        ";
        if ($fromDate && $endDate) {
            $countQuery .= " AND transfer_date BETWEEN :from_date AND :end_date";
        }

        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $countStmt->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $countStmt->bindParam(':type', $typeWithdraw, PDO::PARAM_STR);
        if ($fromDate && $endDate) {
            $countStmt->bindParam(':from_date', $fromDate);
            $countStmt->bindParam(':end_date', $endDate);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        if (empty($data)) {
            return response()->json([
                "code"    => "404",
                "message" => "No transfers found",
                "data"    => null
            ]);
        }

        // 🗺️ Map type & status
        $typeConfig = config('constraint');
        $typeMap = [
            $typeConfig['TYPE_DEPOSIT']  => 'Deposit',
            $typeConfig['TYPE_TRANSFER'] => 'Transfer',
            $typeConfig['TYPE_WITHDRAW'] => 'Withdraw',
        ];

        $statusMap = [
            $typeConfig['STATUS_REQUEST'] => 'Pending',
            $typeConfig['STATUS_APPROVED'] => 'Approved',
            $typeConfig['STATUS_REJECTED'] => 'Rejected',
        ];

        $mappedData = array_map(function ($item) use ($typeMap, $statusMap) {
            $item['type_name']   = $typeMap[$item['type']] ?? 'Unknown';
            $item['status_name'] = $statusMap[$item['status']] ?? 'Unknown';
            return $item;
        }, $data);

        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => [
                "current_page" => $page,
                "per_page"     => $limit,
                "total"        => $total,
                "total_pages"  => ceil($total / $limit),
                "records"      => $mappedData
            ]
        ]);
    }

    public function memberWithdraw(Request $request, SessionService $sessionService)
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
        $accountId = $user->getAccountId();
        $customerCode = $user->getCustomerCode();
        $username = $user->getUsername();
        $groupId = $user->getGroupId();

        // $companyId = "e615a47c-a210-4a13-b3a7-edaa71ec645f";
        // $companyCode = "tmp";
        // $companyName = "KASIRJUDI";
        // $customerId = "D13750EB-0F43-4991-A44F-B7DA770B6DA0";
        // $accountId = "4D97EBB9-092E-4E19-BD97-F5E69149CCA2";
        // $customerCode = "tmp726j641";
        // $username = "narakasir2";
        // $groupId = "3";

        $redisKey = "manual_withdraw/{$customerId}";
        $checkDelay = $this->redisService->get($redisKey);
        if ($checkDelay) {
            $redisExpireIn = $this->redisService->ttl($redisKey);
            return response()->json([
                "code"    => "402",
                "message" => 'Formulir terlalu sering dikirimkan, harap tunggu dan kirimkan lagi setelah '.$redisExpireIn.' menit',
                "data"    => null
            ]);
        }

        $validator = Validator::make($request->all(), [
            'member_bank_id'      => 'required|string',
            'member_bank_name'   => 'required|string',
            'member_account_name'   => 'required|string',
            'member_account_number'   => 'required|string',
            'member_account_id'   => 'required|string',
            'amount'              => 'required|numeric|min:1',
            'ip'                  => 'nullable|ip',
            'domain'              => 'nullable|url',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ]);
        }

        $memberBankId = $request->input('member_bank_id');
        $memberBankName = $request->input('member_bank_name');
        $memberAccountName = $request->input('member_account_name');
        $memberAccountNumber = $request->input('member_account_number');
        $memberAccountId = $request->input('member_account_id');

        $jsonCustomerBank = json_encode([
            'bank_name' => $memberBankName,
            'account_name' => $memberAccountName,
            'account_number' => $memberAccountNumber,
        ]);

        $amount = $request->input('amount'); 
        $ip = $request->input('ip');
        $domain = $request->input('domain');

        $date = Carbon::now()->format('Y-m-d H:i:s');

        $querySetting = "SELECT TOP 1
            id, 
            company_id,
            min_dp,
            max_dp, 
            min_wd, 
            max_wd,
            modulus,
            delay
            FROM pd_wd_settings WITH (NOLOCK)
            WHERE company_id = :company_id";

        $stmtSetting = DB::connection('main')->getPdo()->prepare($querySetting);
        $stmtSetting->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtSetting->execute();
        $DWSettings = $stmtSetting->fetch(PDO::FETCH_ASSOC);
        if (!$DWSettings) {
            return response()->json([
                "code"    => "500",
                "message" => "Withdraw Setting not found",
                "data"    => null
            ]);
        }
        
        $minWd = $DWSettings['min_wd'];
        $maxWd = $DWSettings['max_wd'];

        if (($amount < $minWd) || ($amount > $maxWd)) {
            return response()->json([
                "code"    => "500",
                "message" => 'Minimum Withdraw '.number_format($minWd)
                            .' Maximum Withdraw '.number_format($maxWd),
                "data"    => null
            ]);
        }

        $queryAccount = "SELECT TOP 1
            id, 
            customer_id,
            company_id,
            balance,
            bonus_type,
            is_bonus
            FROM accounts WITH (NOLOCK)
            WHERE id = :account_id
            AND deleted = 0";

        $stmtAcc = DB::connection('main')->getPdo()->prepare($queryAccount);
        $stmtAcc->bindParam(':account_id', $accountId, PDO::PARAM_STR);
        $stmtAcc->execute();
        $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);
       
        if (!$account) {
            return response()->json([
                "code"    => "404",
                "message" => "Account not found",
                "data"    => null
            ]);
        }
        
        $balance = $account['balance'];
        $isBonus = $account['is_bonus'];
        if ($isBonus) {
            return response()->json([
                "code"    => "500",
                "message" => "User join lock bonus, can not withdraw!",
                "data"    => null
            ]);
        }

        if ($balance < $amount) {
             return response()->json([
                "code"    => "402",
                "message" => "Insufficient Balance",
                "data"    => null
            ]);
        }

        $query = "
            SELECT TOP 1 1
            FROM first_withdraws WITH (NOLOCK)
            WHERE customer_id = :customer_id
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmt->execute();
        $firstWithdraw = $stmt->fetch(PDO::FETCH_ASSOC) ? 0 : 1;

        $lastBalance = $balance - $amount;
        $updateBalance = $this->accountService->updateBalance($accountId, $lastBalance);
        
        if (!$updateBalance) {
            return response()->json([
                "code"    => "500",
                "message" => "Update Balance Failed",
                "data"    => null
            ]);
        }
        
        $transferData = [
            'customer_id' => $customerId,
            'account_id' => $accountId,
            'to_account_id' => null,
            'amount' => $amount,
            'image' => null,
            'type' => config('constraint.TYPE_WITHDRAW'),
            'status' => config('constraint.STATUS_REQUEST'),
            'ip_address' => $ip,
            'description' => null,
            'transfer_date' => $date,
            'operator_bank' => null,
            'customer_bank' => $jsonCustomerBank,
            'company_id' => $companyId,
            'customer_bank_name' => $memberBankName,
            'customer_name' => $username,
            'customer_code' => $customerCode,
            'group_id' => $groupId,
            'account_balance' => $balance,
            'f_wd' => $firstWithdraw,
            'company_name' => $companyName,
            'payment_type' => config('constraint.PAYMENT_MANUAL')
        ];
        
        $newTransferId = $this->transferService->insert($transferData);
        if (!$newTransferId) {
            return response()->json([
                "code"    => "500",
                "message" => "Deposit Failed",
                "data"    => null
            ]);
        }
        
        $transactionData = [
            'transfer' => $newTransferId,
            'account_id' => $accountId,
            'begin_balance' => $balance,
            'amount' => - $amount,
            'end_balance' => $lastBalance,
            'type' => strtolower(config('constraint.STRING_WITHDRAW')),
            'status' => config('constraint.STATUS_REQUEST'),
            'ip_address' => $ip,
        ];
        $newTransactionId = $this->transactionService->insert($transactionData);
        if (!$newTransferId) {
            return response()->json([
                "code"    => "500",
                "message" => "Deposit Failed",
                "data"    => null
            ]);
        }

        $transferData['id'] = (int)$newTransferId;
        $newTransferDataObj = (object) $transferData;

        $boPushService = $this->boPushService->withdraw($newTransferDataObj);
        $notificationService = $this->notificationService->update($newTransferDataObj, 'withdraw');
        
        $delay = $DWSettings['delay'];
        if ($delay > 0) {
            $delayInSeconds = max(0, $delay * 60);
            $jsonCache = json_encode([
                'companyId' => $companyId,
                'companyCode' => $companyCode,
                'companyName' => $companyName,
                'customerId' => $customerId,
                'accountId' => $accountId,
                'customerCode' => $customerCode,
                'username' => $username,
            ]);
            // Store the response in Redis for 300 seconds = 5mn
            $this->redisService->set($redisKey, $jsonCache, $delayInSeconds);
        }

        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => $newTransferDataObj
        ]);
    }

}
