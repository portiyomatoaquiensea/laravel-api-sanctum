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

use App\Services\ReferralDetailService;
use App\Services\EncryptService;
use App\Services\NotificationService;
use App\Services\BoPushService;
use Carbon\Carbon;
use PDO;

class ReferralController extends Controller
{

    // Redis
    protected $redisService;
    // Service
    protected $referralDetailService;
    protected $notificationService;
    protected $boPushService;

    public function __construct(
        RedisService $redisService,

        ReferralDetailService $referralDetailService,
        NotificationService $notificationService,
        BoPushService $boPushService,
    ) {
        $this->redisService = $redisService;

        $this->referralDetailService = $referralDetailService;
        $this->notificationService = $notificationService;
        $this->boPushService = $boPushService;
    }

    public function memberReferralCheck(Request $request, SessionService $sessionService)
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

        $queryString = "SELECT TOP 1
            id, 
            company_id,
            username,
            id_number, 
            dob, 
            address,
            phone_number,
            image,
            email,
            status
            FROM referral_details WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id";

        $stmtReferral = DB::connection('main')->getPdo()->prepare($queryString);
        $stmtReferral->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtReferral->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtReferral->execute();
        $referral = $stmtReferral->fetch(PDO::FETCH_ASSOC);
        if ($referral) {
            $typeConfig = config('constraint');
            $statusMap = [
                $typeConfig['STATUS_REQUEST'] => 'Pending',
                $typeConfig['STATUS_APPROVED'] => 'Approved',
                $typeConfig['STATUS_REJECTED'] => 'Rejected',
            ];
            $referral['status_name'] = $statusMap[$referral['status']] ?? 'Unknown';
            return response()->json([
                'code'    => '200',
                'message' => 'Successfully',
                'data'    => $referral,
            ]);
        }
        
        return response()->json([
            'code'    => '404',
            'message' => 'Referral Not Found',
            'data'    => null,
        ]);
    }


    public function memberReferralCreate(Request $request, SessionService $sessionService)
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

        $validator = Validator::make($request->all(), [
            'website_url' => 'required|url',
            'id_number' => 'required|string|max:20',
            'username' => 'required|string|min:3|max:50',
            'dob' => 'required|date|before:today',
            'address' => 'required|string|max:255',
            'phone_number' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            'email' => 'required|email|max:100',
            'otp' => 'required|string',
            'image' => ['required', 'string', 'regex:/^[\w\-\_\/]+\.(jpg|jpeg|png)$/i'],
            'ip' => 'required|string',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ]);
        }

        $websiteUrl = $request->input('website_url');
        $idNumber = $request->input('id_number');
        $username = $request->input('username');
        $dob = $request->input('dob');
        $address = $request->input('address');
        $phoneNumber = $request->input('phone_number');
        $email = $request->input('email');
        $otp = $request->input('otp');
        $image = $request->input('image');
        $ip = $request->input('ip');

        $redisKey = "opt/{$companyCode}/{$customerId}/{$phoneNumber}";
        $checkCacheOtp = $this->redisService->get($redisKey);
        if (!$checkCacheOtp) {
            return response()->json([
                "code"    => "500",
                "message" => "Your OTP is expired or Invalid. Please try new OTP",
                "data"    => null
            ]);
        }
        $otpData = json_decode($checkCacheOtp);
        if (!$otpData) {
            return response()->json([
                "code"    => "500",
                "message" => "Your OTP is expired or Invalid. Please try new OTP",
                "data"    => null
            ]);
        }
        $otpCode = $otpData->code;
        if ($otpCode != $otp) {
            return response()->json([
                "code"    => "500",
                "message" => "Invalid OTP. Please try new OTP",
                "data"    => null
            ]);
        }

        // Check Referral
        $queryString = "SELECT TOP 1
            id, 
            company_id,
            username,
            id_number, 
            dob, 
            address,
            phone_number,
            image,
            email,
            status
            FROM referral_details WITH (NOLOCK)
            WHERE customer_id = :customer_id
            AND company_id = :company_id";

        $stmtReferral = DB::connection('main')->getPdo()->prepare($queryString);
        $stmtReferral->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
        $stmtReferral->bindParam(':company_id', $companyId, PDO::PARAM_STR);
        $stmtReferral->execute();
        $referral = $stmtReferral->fetch(PDO::FETCH_ASSOC);
        if ($referral) {
            $typeConfig = config('constraint');
            $statusMap = [
                $typeConfig['STATUS_REQUEST'] => 'Pending',
                $typeConfig['STATUS_APPROVED'] => 'Approved',
                $typeConfig['STATUS_REJECTED'] => 'Rejected',
            ];
            $referral['status_name'] = $statusMap[$referral['status']] ?? 'Unknown';

            if ($referral['status_name'] == 'Pending') {
                $message = 'Your Referral is being proccessing please contact CS';
            }
            if ($referral['status_name'] == 'Approved') {
                $message = 'You are have actived Referral. For more info contact CS';
            }
            if ($referral['status_name'] == 'Rejected') {
                $message = 'You are have disactive Referral. For more info contact CS';
            }
            return response()->json([
                'code'    => '500',
                'message' => $message,
                'data'    => $referral,
            ]);
        }

        $referralDetailData = [
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'username' => $username,
            'id_number' => $idNumber,
            'dob' => $dob,
            'address' => $address,
            'phone_number' => $phoneNumber,
            'website_url' => $websiteUrl,
            'image' => $image,
            'ip' => $ip,
            'email' => $email,
            'status' => 0,
        ];
        $newReferralDetailId = $this->referralDetailService->insert($referralDetailData);
        if (!$newReferralDetailId) {
            return response()->json([
                "code"    => "500",
                "message" => "Failed to create Referral",
                "data"    => null
            ]);
        }

        $referralDetailData['id'] = $newReferralDetailId;
        $referralDetailData['group_id'] = $groupId;
        $referralDetailData['company_name'] = $companyName;
        $referralDetailData['customer_code'] = $companyCode;
        $newReferralDetailObj = (object) $referralDetailData;

        $kycNotificationService = $this->notificationService->update($newReferralDetailObj, 'kyc_count');
        $kycBoPushService = $this->boPushService->kyc($newReferralDetailObj);
       
        return response()->json([
            'code'    => '200',
            'message' => 'Successfully',
            'data'    => $newReferralDetailObj,
        ]);
        
    }

    public function memberReferralList(Request $request, SessionService $sessionService)
    {
        // 🔒 Validate user session
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

        $validator = Validator::make($request->all(), [
            // ✅ New pagination & filter rules
            'page' => 'required|integer|min:1',
            'limit' => 'required|integer|min:1|max:100',
            'by_name' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "code" => "422",
                "message" => "Missing or invalid parameters",
                "data" => null
            ]);
        }

        // 🔒 Static user for testing
    
        // 📄 Pagination
        $page  = max((int)$request->input('page',1),1);
        $limit = max((int)$request->input('limit',10),1);
        $offset = ($page-1)*$limit;

        // 🔍 Optional username filter
        $searchUsername = trim($request->input('by_name',''));

        $pdo = DB::connection('main')->getPdo();

        // 1️⃣ Fetch totals in a single query
        $totalsQuery = "
            SELECT
                COUNT(DISTINCT customer.id) AS total_register,
                COUNT(DISTINCT CASE WHEN bonus_cm_sent_detail.mode='referral' THEN customer.id END) AS total_referral,
                ISNULL(SUM(CASE WHEN bonus_cm_sent_detail.mode='referral' THEN bonus_cm_sent_detail.bonus END),0) AS total_bonus
            FROM customers AS customer WITH (NOLOCK)
            LEFT JOIN bonus_cm_sent_details AS bonus_cm_sent_detail WITH (NOLOCK)
                ON bonus_cm_sent_detail.customer_id = customer.id
            WHERE customer.parent_id = :customer_id
            AND customer.company_id = :company_id
        ";
        if(!empty($searchUsername)){
            $totalsQuery .= " AND customer.username LIKE :username";
        }

        $totalsStmt = $pdo->prepare($totalsQuery);
        $totalsStmt->bindParam(':customer_id',$customerId);
        $totalsStmt->bindParam(':company_id',$companyId);
        if(!empty($searchUsername)){
            $likeUsername = '%'.$searchUsername.'%';
            $totalsStmt->bindParam(':username',$likeUsername);
        }
        $totalsStmt->execute();
        $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);

        // 2️⃣ Fetch paginated referral data
        $queryReferral = "
            SELECT 
                customer.id AS customer_id,
                customer.username,
                bonus_cm_sent_detail.id AS bonus_cm_sent_detail_id,
                bonus_cm_sent_detail.bonus AS bonus_cm_sent_detail_bonus_bonus,
                bonus_cm_sent_detail.status AS bonus_cm_sent_detail_status,
                bonus_cm_sent_detail.modified AS bonus_cm_sent_detail_modified
            FROM customers AS customer WITH (NOLOCK)
            LEFT JOIN bonus_cm_sent_details AS bonus_cm_sent_detail WITH (NOLOCK)
                ON bonus_cm_sent_detail.customer_id = customer.id
            WHERE customer.parent_id = :customer_id
            AND customer.company_id = :company_id
            AND bonus_cm_sent_detail.mode = 'referral'
        ";
        if(!empty($searchUsername)){
            $queryReferral .= " AND customer.username LIKE :username";
        }
        $queryReferral .= "
            ORDER BY bonus_cm_sent_detail.modified DESC
            OFFSET :offset ROWS
            FETCH NEXT :limit ROWS ONLY
        ";

        $stmt = $pdo->prepare($queryReferral);
        $stmt->bindParam(':customer_id',$customerId);
        $stmt->bindParam(':company_id',$companyId);
        if(!empty($searchUsername)){
            $stmt->bindParam(':username',$likeUsername);
        }
        $stmt->bindParam(':offset',$offset,PDO::PARAM_INT);
        $stmt->bindParam(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 🗺️ Map status
        $statusMap = ['0'=>'Pending','1'=>'Approved','2'=>'Rejected'];
        $mappedData = array_map(function($item) use($statusMap){
            $item['bonus_cm_sent_detail_status_name'] = $statusMap[$item['bonus_cm_sent_detail_status']] ?? 'Unknown';
            return $item;
        }, $data);

        return response()->json([
            "code"=>200,
            "message"=>"Successfully fetched referral list",
            "data"=>[
                "summary"=>[
                    "total_register" => (int)$totals['total_register'],
                    "total_referral" => (int)$totals['total_referral'],
                    "total_bonus"    => (float)$totals['total_bonus']
                ],
                "current_page"=>$page,
                "per_page"=>$limit,
                "total" => (int)$totals['total_referral'],
                "total_pages"=>ceil($totals['total_referral']/$limit),
                "records"=>$mappedData
            ]
        ]);
    }

}
