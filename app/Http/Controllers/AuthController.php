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

use App\Services\CustomerService;
use App\Services\AccountService;
use App\Services\EncryptService;
use App\Services\UltraMessageService;

use Carbon\Carbon;
use PDO;

class AuthController extends Controller
{

    private $WA_COUNTRY_CODE = '+62';
    
    protected $sessionService;
    // Service
    protected $customerService;
    protected $accountService;
    protected $encryptService;
    protected $ultraMessageService;

    public function __construct(
        SessionService $sessionService,

        CustomerService $customerService,
        AccountService $accountService,
        EncryptService $encryptService,
        UltraMessageService $ultraMessageService,
    ) {
        $this->sessionService = $sessionService;

        $this->customerService = $customerService;
        $this->accountService = $accountService;
        $this->encryptService = $encryptService;
        $this->ultraMessageService = $ultraMessageService;
    }

    // Login with hardcoded credentials
    public function login(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $companyCode = $request->header('COMPANY_CODE');

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'user_agent' => 'nullable|string',
            'login_date' => 'nullable|date',
            'login_ip' => 'nullable|ip',
            'ip' => 'nullable|ip',
            'domain' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ], 422);

        }

        $username = $request->input('username');
        $password = $request->input('password');

        $queryCustomer = "SELECT TOP 1
            id, 
            username,
            code,
            point, 
            password, 
            email,
            phone_number,
            lock_bonus_date
            FROM customers WITH (NOLOCK) WHERE 
            username = :username
            AND company_id = :company_id
            AND blocked = 0
            AND deleted = 0";

        $stmtCus = DB::connection('main')->getPdo()->prepare($queryCustomer);
        $stmtCus->bindParam(':username', $username, PDO::PARAM_STR);
        $stmtCus->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmtCus->execute();
        $customer = $stmtCus->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            return response()->json([
                "code"    => "401",
                "message" => "Wrong Username/Password",
                "data"    => null
            ], 401);
        }
        $user_password = trim($customer['password']);
        if (!Hash::check($password, $user_password)) {
            return response()->json([
                "code"    => "401",
                "message" => "Wrong Username/Password",
                "data"    => null
            ], 401);
        }

        $customerId = $customer['id'];
        $customerCode = $customer['code'];
        $customerPoint = $customer['point'];
        $customerUsername = $customer['username'];
        $customerPhoneNumber = $customer['phone_number'];
        $customerLockBonusDate = $customer['lock_bonus_date'];
        $customerCurrency = 'IDR';

        
        // Kick out previous sessions
        $this->sessionService->kickOutUser($customerId);

        $queryAcc = "SELECT TOP 1
            id, 
            bank_id,
            company_id,
            customer_id, 
            name, 
            number,
            bonus,
            balance,
            is_bonus,
            bonus_type
            FROM accounts WITH (NOLOCK) WHERE 
            customer_id = :customer_id
            AND company_id = :company_id
            AND deleted = 0";

        $stmtAcc = DB::connection('main')->getPdo()->prepare($queryAcc);
        $stmtAcc->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmtAcc->execute();
        $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            return response()->json([
                "code"    => "401",
                "message" => "Wrong Username/Password",
                "data"    => null
            ], 401);
        }

        $accountId = $account['id'];
        $accountBonus = $account['bonus'];
        $accountBalance = $account['balance'];
        $isBonus = $account['is_bonus'] ?? null;
        $joinedBonus = ($isBonus === '1' || $isBonus === 1) ? 'YES' : 'NO';
        $bonusType = $account['bonus_type'];
        
        $queryCom = "SELECT TOP 1
            id, 
            group_id,
            code,
            name,
            sbo_agent
            FROM companies WITH (NOLOCK) 
            WHERE id = :company_id
            AND code = :company_code
            AND deleted = 0";

        $stmtCom = DB::connection('main')->getPdo()->prepare($queryCom);
        $stmtCom->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmtCom->bindParam(':company_code', $companyCode, PDO::PARAM_STR);
        $stmtCom->execute();
        $company = $stmtCom->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            return response()->json([
                "code"    => "401",
                "message" => "Internal Server error",
                "data"    => null
            ], 401);
        }

        $groupId = $company['group_id'];
        $sboAgent = $company['sbo_agent'];
        $companyName = $company['name'];
        
        $queryLockBonusLog = "SELECT TOP 1
            setting,
            action
            FROM lock_bonus_logs WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id";

        $stmtBonusLog = DB::connection('main')->getPdo()->prepare($queryLockBonusLog);
        $stmtBonusLog->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtBonusLog->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmtBonusLog->execute();
        $lockBonusLog = $stmtBonusLog->fetch(PDO::FETCH_ASSOC);
        $lockBonusAction = $lockBonusLog['action'] ?? '';
        $lockBonusDuration = json_decode($lockBonusLog['setting'] ?? '', true)['duration'] ?? null;
        $lockBonusTurnover = json_decode($lockBonusLog['setting'] ?? '', true)['turnover'] ?? null;
        $lockBonusMemberAmount = json_decode($lockBonusLog['setting'] ?? '', true)['member_amount'] ?? null;
        $lockBonusGetBonus = json_decode($lockBonusLog['setting'] ?? '', true)['get_bonus'] ?? null;

        $request->session()->put('user', [
            'id' => $customerId,
            'company_id' => $companyKey,
            'company_code' => $companyCode,
            'company_name' => $companyName,
            'customer_code' => $customerCode,
            'account_id' => $accountId,
            'username' => $customerUsername,
            'group_id' => $groupId,
            'sbo_agent' => $sboAgent,
            'point' => $customerPoint,
            'phone_number' => $customerPhoneNumber,
            'currency' => $customerCurrency,
            'joined_bonus' => $joinedBonus,
            'bonus_type' => $bonusType,
            'lock_bonus_date'  => $customerLockBonusDate,
            'lock_bonus_action'  => $lockBonusAction,
            'lock_bonus_duration'  => $lockBonusDuration,
            'lock_bonus_turover'  => $lockBonusTurnover,
            'lock_bonus_member_amount'  => $lockBonusMemberAmount,
            'lock_bonus_get_bonus'  => $lockBonusGetBonus,
        ]);
        // Regenerate session ID and CSRF token
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return response()->json([
            "code"    => "200",
            'message' => 'Logged in Successfull',
            'data' => $request->session()->get('user'),
        ]);
    }

    // Logout by clearing session
    public function logout(Request $request)
    {
        $request->session()->forget('user');        // Remove user from session
        $request->session()->invalidate();          // Invalidate the session
        $request->session()->regenerateToken();     // Regenerate CSRF token
        return response()->json([
            "code"    => "200",
            "message" => "Logged out Successfully",
            "data"    => null
        ]);
    }

    // Return current user from session
    public function me(Request $request)
    {
        $user = $request->session()->get('user');
        if ($user) {
            return response()->json([
                "code"    => "200",
                "message" => "Successfully",
                "data"    => $user
            ]);
        }
        return response()->json([
            "code"    => "401",
            "message" => "Invalid credentials",
            "data"    => null
        ], 401);
    }

    public function memberChangePassword(Request $request, SessionService $sessionService)
    {
        // ✅ Ensure user is authenticated
        $user = $sessionService->getUser($request);
        if (!$user) {
            return response()->json([
                'code'    => '403',
                'message' => 'Please login first',
                'data'    => null,
            ]);
        }

        $companyId  = $user->getCompanyId();
        $customerId = $user->getId();

        // ✅ Validate input
        $validator = Validator::make($request->all(), [
            'password'              => 'required|string',
            'new_password'          => 'required|string|min:6',
            'confirm_new_password'  => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => '422',
                'message' => $validator->errors()->first() ?? 'Missing required parameters',
                'data'    => null,
            ]);
        }

        $oldPassword = $request->input('password');
        $newPassword = $request->input('new_password');

        // ✅ Prevent using the same password
        if ($newPassword === $oldPassword) {
            return response()->json([
                'code'    => '400',
                'message' => 'New password cannot be the same as old password.',
                'data'    => null,
            ]);
        }

        // ✅ Retrieve customer record
        $query = "
            SELECT TOP 1 id, password
            FROM customers WITH (NOLOCK)
            WHERE id = :customer_id
            AND company_id = :company_id
            AND blocked = 0
            AND deleted = 0
        ";

        $pdo  = DB::connection('main')->getPdo();
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':customer_id' => $customerId,
            ':company_id'  => $companyId,
        ]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            return response()->json([
                'code'    => '404',
                'message' => 'Customer not found.',
                'data'    => null,
            ]);
        }

        // ✅ Verify old password
        if (!Hash::check($oldPassword, trim($customer['password']))) {
            return response()->json([
                'code'    => '401',
                'message' => 'Incorrect current password.',
                'data'    => null,
            ]);
        }

        // ✅ Update password securely
        $updated = $this->customerService->updatePassword($customerId, $newPassword);

        if (!$updated) {
            return response()->json([
                'code'    => '500',
                'message' => 'Failed to update password. Please try again later.',
                'data'    => null,
            ]);
        }

        return response()->json([
            'code'    => '200',
            'message' => 'Password changed successfully.',
            'data'    => null,
        ]);
    }


    public function memberBalance(Request $request, SessionService $sessionService)
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
        $accountId = $user->getAccountId();
      
        $queryAcc = "SELECT TOP 1
            id, 
            bank_id,
            company_id,
            customer_id, 
            name, 
            number,
            bonus,
            balance
            FROM accounts WITH (NOLOCK) 
            WHERE id = :account_id
            AND customer_id = :customer_id
            AND company_id = :company_id
            AND deleted = 0";

        $stmtAcc = DB::connection('main')->getPdo()->prepare($queryAcc);
        $stmtAcc->bindParam(':account_id', $accountId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtAcc->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtAcc->execute();
        $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            return response()->json([
                "code"    => "401",
                "message" => "Wrong Username/Password",
                "data"    => null
            ], 401);
        }

        return response()->json([
            "code"    => "200",
            "message" => "Successfully",
            "data"    => $account
        ]);
    }


    public function register(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $companyCode = $request->header('COMPANY_CODE');

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'confirm_password' => 'required|string',
            'contact' => 'required|string',
            'bank_id' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            'reference_code' => 'nullable|string',
            'user_agent' => 'nullable|string',
            'login_date' => 'nullable|date',
            'login_ip' => 'nullable|ip',
            'ip' => 'nullable|ip',
            'domain' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ]);

        }

        $username = $request->input('username');
        $password = $request->input('password');
        $hashedPassword = Hash::make($password);
        $confirmPassword = $request->input('confirm_password');

        if ($password !== $confirmPassword) {
            return response()->json([
                "code"    => "422",
                "message" => "Password and Comfirm Password is not Match.",
                "data"    => null
            ]);
        }

        $contact = $request->input('contact');
        $bankId = $request->input('bank_id');
        $accountName = $request->input('account_name');
        $accountNumber = $request->input('account_number');
        $referenceCode = $request->input('reference_code');
        $userAgent = $request->input('user_agent');
        $loginDate = $request->input('login_date');
        $ip = $request->input('ip');
        $domain = $request->input('domain');

        $queryCustomer = " SELECT TOP 1 1
            FROM customers WITH (NOLOCK)
            WHERE username = :username
            AND company_id = :company_id";

        $stmtCus = DB::connection('main')->getPdo()->prepare($queryCustomer);
        $stmtCus->bindParam(':username', $username, PDO::PARAM_STR);
        $stmtCus->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmtCus->execute();
        $customer = $stmtCus->fetch(PDO::FETCH_ASSOC);
        $encryptKey = $this->encryptService->encrypt($password);

        if ($customer) {
            return response()->json([
                "code"    => "402",
                'message' => 'User Already Taken',
                "data"    => null
            ]);
        }
        
        $customerUniqueCode = $this->customerService->uniqueCode($companyCode);
        $parentId = $this->customerService->findReferral($referenceCode);
        $phonNumber = preg_replace('/^\+?'.$this->WA_COUNTRY_CODE.'|\|1|\D/', '', $contact);

        $checkWhatsapp = $this->ultraMessageService->checkWhatsapp($phonNumber);
        $isWa = isset($checkWhatsapp['is_wa']) ? $checkWhatsapp['is_wa'] : 'E';
        $newCustomer = [
            'parent_id' => $parentId,
            'company_id' => $companyKey,
            'code' => $customerUniqueCode,
            'bank_id' => $bankId,
            'username' => $username,
            'password' => $hashedPassword,
            'account_name' => trim($accountName),
            'account_number' => trim($accountNumber),
            'phone_number' => $phonNumber,
            'status' => 1,
            'tmp_id' => 1,
            'deleted' => 0,
            'blocked' => 0,
            'domain' => $domain,
            'currency_id' => 1,
            'register_ip' => $ip,
            'referrer_link' => $referenceCode,
            'is_telegram' => 0,
            'telegram_id' => null,
            'is_wa' => $isWa,
        ];

        $newCustomerId = $this->customerService->create($newCustomer);

        if (!$newCustomerId) {
            return response()->json([
                "code"    => "402",
                'message' => 'Failed to create Member',
                "data"    => null
            ]);
        }

        $newAccount = [
            'bank_id' => $bankId,
            'company_id' => $companyKey,
            'customer_id' => $newCustomerId,
            'name' => $accountName,
            'number' => $accountNumber,
            'house' => 0,
            'main' => 1,
            'bonus' => 0.00,
            'balance' => 0.00,
            'deleted' => 0,
            'bonus_type' => null,
            'is_bonus' => 0.00,
            'regiser_date' => $loginDate,
        ];

        $newAccountId = $this->accountService->create($newAccount);
        if (!$newAccountId) {
            return response()->json([
                "code"    => "402",
                'message' => 'Failed to create Member',
                "data"    => null
            ]);
        }

        return response()->json([
            "code"    => "200",
            'message' => 'Register in Successfull',
            'data' => null,
        ]);
    }

}
