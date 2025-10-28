<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\OpenUrl;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use Carbon\Carbon;

class HomeController extends Controller
{

    public function slider(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        // $companyCode  = $request->header('COMPANY_CODE');
        // $companyName = $request->header('COMPANY_NAME');

        $companySliderIds = \DB::connection('main')
            ->table('slider_companies')
            ->where('company_id', $companyKey)
            ->pluck('slider_id')
            ->toArray(); // make sure it's an array

        $sliders = \DB::connection('main')
            ->table('sliders')
            ->where(function ($query) use ($companyKey, $companySliderIds) {
                $query->where('company_id', $companyKey);

                if (!empty($companySliderIds)) {
                    $query->orWhereIn('id', $companySliderIds);
                }
            })
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->orderBy('sort', 'asc')
            ->get();

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $sliders
        ]);
    }

    public function bonus(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');

        $companyBonusIds = \DB::connection('main')
            ->table('promotion_companies')
            ->where('company_id', $companyKey)
            ->pluck('promotion_id')
            ->toArray(); // make sure it's an array

        $sliders = \DB::connection('main')
            ->table('promotions')
            ->where(function ($query) use ($companyKey, $companyBonusIds) {
                $query->where('company_id', $companyKey);

                if (!empty($companyBonusIds)) {
                    $query->orWhereIn('id', $companyBonusIds);
                }
            })
            ->where('status', 1)
            ->where('deleted', 0)
            ->where('type', 'bonus')
            ->orderBy('sort', 'asc')
            ->get();

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $sliders
        ]);
    }

    public function promotion(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');

        $companyPromotionIds = \DB::connection('main')
            ->table('promotion_companies')
            ->where('company_id', $companyKey)
            ->pluck('promotion_id')
            ->toArray(); // make sure it's an array

        $data = \DB::connection('main')
            ->table('promotions')
            ->where(function ($query) use ($companyKey, $companyPromotionIds) {
                $query->where('company_id', $companyKey);

                if (!empty($companyPromotionIds)) {
                    $query->orWhereIn('id', $companyPromotionIds);
                }
            })
            ->where('status', 1)
            ->where('deleted', 0)
            ->where('type', 'promotion')
            ->orderBy('sort', 'asc')
            ->get();

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $data
        ]);
    }

    public function bank(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');

        $data = \DB::connection('main')
            ->table('banks as b')
            ->join('company_banks as cb', 'b.id', '=', 'cb.bank_id')
            ->where('cb.company_id', $companyKey)
            ->where('cb.status', 1)
            ->where('b.status', 1)
            ->where('b.deleted', 0)
            ->select(
                'b.id as bank_id',
                'b.name',
                'b.url',
                'b.image',
                'b.sort as bank_sort',
                'b.status as bank_status',
                'b.description',
                'b.deleted',
                'b.created as bank_created',
                'b.modified as bank_modified',
                'b.wallet_type',
                'b.alias_name',
                'b.alias_name3',
                'b.alias_name4',
                'cb.id as company_bank_id',
                'cb.company_id',
                'cb.bank_id as company_bank_bank_id',
                'cb.sort as company_bank_sort',
                'cb.status as company_bank_status',
                'cb.created as company_bank_created',
                'cb.modified as company_bank_modified'
            )
            ->orderBy('cb.sort', 'asc')
            ->get();

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $data
        ]);
    }

    public function contact(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');

        $data = \DB::connection('main')
            ->table('contacts as c')
            ->join('contact_details as cd', 'c.id', '=', 'cd.contact_id')
            ->where('cd.company_id', $companyKey)
            ->where('c.deleted', 0)
            ->select(
                'c.id as contact_id',
                'c.code',
                'c.name as contact_name',
                'c.deleted',
                'c.created as contact_created',
                'c.modified as contact_modified',
                'c.image',
                'cd.id as contact_detail_id',
                'cd.company_id',
                'cd.user_id',
                'cd.contact_id as contact_detail_contact_id',
                'cd.detail',
                'cd.url',
                'cd.created as contact_detail_created',
                'cd.modified as contact_detail_modified'
            )
            ->orderBy('c.name', 'asc')
            ->get();


        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $data
        ]);
    }

    public function providerCategory(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $categoryId = $request->input('category_id');

        $queryString = "SELECT p.id as providerId
                        , p.category_id as providerCategoryId
                        , p.code as providerCode
                        , p.name as providerName
                        , p.status as providerStatus
                        , cat.name as categoryName
                        , cat.alias_name as categoryAliasName
                        , d.is_maintenance AS downlinePageDetailIsMaintenance
                        , pm.status AS providerMaintenanceStatus
                        , pm.start_date AS providerMaintenanceStartDate
                        , pm.end_date AS providerMaintenanceEndDate
                        , cpm.provider_maintenance_id AS companyProviderMaintenanceProviderMaintenanceId
                    FROM providers p
                    JOIN downline_page_details d WITH (NOLOCK) ON p.id = d.provider_id
                    JOIN downline_pages dp WITH (NOLOCK) ON d.downline_page_id = dp.id
                    JOIN categorys cat WITH (NOLOCK) ON p.category_id = cat.id
                    LEFT JOIN provider_maintenances pm WITH (NOLOCK) ON p.id = pm.provider_id
                    LEFT JOIN company_provider_maintenances cpm WITH (NOLOCK) ON pm.id = cpm.provider_maintenance_id
                    AND cpm.company_id = dp.company_id
                    WHERE d.is_maintenance = 0
                    AND p.status = 1
                    AND p.is_deleted = 0
                    AND dp.company_id = :company_id
                    AND dp.category_id = :category_id
                    ORDER BY d.sort ASC";

        $stmt = DB::connection('main')->getPdo()->prepare($queryString);
        $stmt->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        if ($result) {
            foreach ($result as $game) {
                $isMaintenance = false;
                $startDate = null;
                $endDate = null;

                // Combine conditions to reduce nesting
                if ($game['providerMaintenanceStatus'] && $game['companyProviderMaintenanceProviderMaintenanceId']) {
                    $startDate = Carbon::parse($game['providerMaintenanceStartDate']);
                    $endDate = Carbon::parse($game['providerMaintenanceEndDate']);
                    $nowStart = Carbon::now()->setSeconds(0);
                    $nowEnd = Carbon::now()->setSeconds(59);

                    // Check maintenance status
                    $isMaintenance = $startDate->lessThanOrEqualTo($nowStart) && $endDate->greaterThanOrEqualTo($nowEnd);
                }

                // Helper function to format date adjustments
                $formatDateAdjustment = function ($date) {
                    return [
                        'to_minute' => $date ? $date->diffInMinutes($date->copy()->startOfDay()) : '',
                        'y' => $date ? $date->year : '',
                        'm' => $date ? $date->month : '',
                        'd' => $date ? $date->day : '',
                        'h' => $date ? $date->hour : '',
                        'i' => $date ? $date->minute : '',
                        's' => $date ? $date->second : '',
                    ];
                };

                $data[] = [
                    'id' => $game['providerId'],
                    'name' => $game['providerName'],
                    'provider' => $game['providerCode'],
                    'code' => $game['providerCode'],
                    'category_id' => $game['providerCategoryId'],
                    'category_name' => $game['categoryName'],
                    'category_alias_name' => $game['categoryAliasName'],
                    'provider_maintenances' => $isMaintenance,
                    'provider_maintenances' => $isMaintenance,
                    'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : '',
                    'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : '',
                    'adjust_start_date' => $formatDateAdjustment($startDate),
                    'adjust_end_date' => $formatDateAdjustment($endDate),
                ];
            }
        }

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $data
        ]);
    }

    public function popularGame(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');

        $providers = [
            config('providerCode.PP'),
            config('providerCode.PG'),
            config('providerCode.S88'),
            config('providerCode.HB'),
            // config('providerCode.FG'),
            config('providerCode.GW'),
            config('providerCode.MG'),
            config('providerCode.BS'),
            config('providerCode.SFS'),
            config('providerCode.SJL'),
            config('providerCode.SJKS'),
            config('providerCode.SPS'),
            config('providerCode.STGS'),
            config('providerCode.SSAFB'),
            config('providerCode.SSG'),
            config('providerCode.SRTG'),
            config('providerCode.SLCQ9')
        ];

        $allGames = collect();

        foreach ($providers as $provider) {
            $queryString = "
                SELECT TOP 5 *
                FROM games WITH (NOLOCK)
                WHERE provider = :provider
                AND show = 1
                AND maintenance = 0
                AND deleted = 0
                ORDER BY sort ASC, rtp DESC
            ";

            $games = DB::connection('main')->select($queryString,
            ['provider' => $provider]);

            // Merge results into the collection
            $allGames = $allGames->merge($games);
        }

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $allGames
        ]);
    }

    public function megaMenu(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $queryString = "SELECT
                        m.id AS menuId
                        , m.name AS menuName
                        , m.code AS menuCode
                        , p.id as providerId
                        , p.category_id as providerCategoryId
                        , p.code as providerCode
                        , p.name as providerName
                        , p.status as providerStatus
                        , cat.name as categoryName
                        , cat.alias_name as categoryAliasName
                        , dpd.is_maintenance AS downlinePageDetailIsMaintenance
                        , pm.status AS providerMaintenanceStatus
                        , pm.start_date AS providerMaintenanceStartDate
                        , pm.end_date AS providerMaintenanceEndDate
                        , cpm.provider_maintenance_id AS companyProviderMaintenanceProviderMaintenanceId
                    FROM menu m
                    LEFT JOIN menu_items AS mi WITH (NOLOCK)
                        ON mi.menu_id = m.id

                    LEFT JOIN downline_pages AS dp WITH (NOLOCK)
                        ON m.downline_page_id = dp.id
                        AND dp.company_id = m.company_id

                    LEFT JOIN downline_page_details AS dpd WITH (NOLOCK)
                         ON (dpd.downline_page_id = dp.id OR dpd.id = mi.downline_page_detail_id)

                    LEFT JOIN providers p WITH (NOLOCK) ON dpd.provider_id = p.id
                    LEFT JOIN categorys cat WITH (NOLOCK) ON p.category_id = cat.id

                    LEFT JOIN provider_maintenances pm WITH (NOLOCK) ON p.id = pm.provider_id
                    LEFT JOIN company_provider_maintenances cpm WITH (NOLOCK)
                        ON cpm.provider_maintenance_id = dpd.provider_id AND cpm.company_id = m.company_id

                    WHERE
                        m.company_id = :company_id
                    ORDER BY
                        m.sort ASC
                        , mi.sort ASC
                        , dpd.sort ASC";

        $stmt = DB::connection('main')->getPdo()->prepare($queryString);
        $stmt->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        if ($result) {
            foreach ($result as $game) {
                $isMaintenance = false;
                $startDate = null;
                $endDate = null;

                // Combine conditions to reduce nesting
                if ($game['providerMaintenanceStatus'] && $game['companyProviderMaintenanceProviderMaintenanceId']) {
                    $startDate = Carbon::parse($game['providerMaintenanceStartDate']);
                    $endDate = Carbon::parse($game['providerMaintenanceEndDate']);
                    $nowStart = Carbon::now()->setSeconds(0);
                    $nowEnd = Carbon::now()->setSeconds(59);

                    // Check maintenance status
                    $isMaintenance = $startDate->lessThanOrEqualTo($nowStart) && $endDate->greaterThanOrEqualTo($nowEnd);
                }

                // Helper function to format date adjustments
                $formatDateAdjustment = function ($date) {
                    return [
                        'to_minute' => $date ? $date->diffInMinutes($date->copy()->startOfDay()) : '',
                        'y' => $date ? $date->year : '',
                        'm' => $date ? $date->month : '',
                        'd' => $date ? $date->day : '',
                        'h' => $date ? $date->hour : '',
                        'i' => $date ? $date->minute : '',
                        's' => $date ? $date->second : '',
                    ];
                };

                $menuName = $game['menuName'];
                $menuCode = $game['menuCode'];

                $data[$menuCode]['menu_name'] = $menuName;
                $data[$menuCode]['menu_code'] = $menuCode;

                $data[$menuCode]['game'][] = [
                    'id' => $game['providerId'],
                    'name' => $game['providerName'],
                    'provider' => $game['providerCode'],
                    'code' => $game['providerCode'],
                    'category_id' => $game['providerCategoryId'],
                    'category_name' => $game['categoryName'],
                    'category_alias_name' => $game['categoryAliasName'],
                    'provider_maintenances' => $isMaintenance,
                    'provider_maintenances' => $isMaintenance,
                    'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : '',
                    'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : '',
                    'adjust_start_date' => $formatDateAdjustment($startDate),
                    'adjust_end_date' => $formatDateAdjustment($endDate),
                ];
            }
        }

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $data
        ]);
    }

    public function megaMenuGame(Request $request)
    {
        $companyKey = $request->header('COMPANY_KEY');
        $urlName = $request->input('menu_code');

        $queryString = "SELECT
                        m.id AS menuId
                        , m.name AS menuName
                        , m.code AS menuCode
                        , p.id as providerId
                        , p.category_id as providerCategoryId
                        , p.code as providerCode
                        , p.name as providerName
                        , p.status as providerStatus
                        , cat.name as categoryName
                        , cat.alias_name as categoryAliasName
                        , dpd.is_maintenance AS downlinePageDetailIsMaintenance
                        , pm.status AS providerMaintenanceStatus
                        , pm.start_date AS providerMaintenanceStartDate
                        , pm.end_date AS providerMaintenanceEndDate
                        , cpm.provider_maintenance_id AS companyProviderMaintenanceProviderMaintenanceId
                    FROM menu m
                    LEFT JOIN menu_items AS mi WITH (NOLOCK)
                        ON mi.menu_id = m.id

                    LEFT JOIN downline_pages AS dp WITH (NOLOCK)
                        ON m.downline_page_id = dp.id
                        AND dp.company_id = m.company_id

                    LEFT JOIN downline_page_details AS dpd WITH (NOLOCK)
                         ON (dpd.downline_page_id = dp.id OR dpd.id = mi.downline_page_detail_id)

                    LEFT JOIN providers p WITH (NOLOCK) ON dpd.provider_id = p.id
                    LEFT JOIN categorys cat WITH (NOLOCK) ON p.category_id = cat.id

                    LEFT JOIN provider_maintenances pm WITH (NOLOCK) ON p.id = pm.provider_id
                    LEFT JOIN company_provider_maintenances cpm WITH (NOLOCK)
                        ON cpm.provider_maintenance_id = dpd.provider_id AND cpm.company_id = m.company_id

                    WHERE
                        m.company_id = :company_id
                    AND m.code = :menu_code
                    ORDER BY
                        m.sort ASC
                        , mi.sort ASC
                        , dpd.sort ASC";

        $stmt = DB::connection('main')->getPdo()->prepare($queryString);
        $stmt->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
        $stmt->bindParam(':menu_code', $urlName, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        if ($result) {
            foreach ($result as $game) {
                $isMaintenance = false;
                $startDate = null;
                $endDate = null;

                // Combine conditions to reduce nesting
                if ($game['providerMaintenanceStatus'] && $game['companyProviderMaintenanceProviderMaintenanceId']) {
                    $startDate = Carbon::parse($game['providerMaintenanceStartDate']);
                    $endDate = Carbon::parse($game['providerMaintenanceEndDate']);
                    $nowStart = Carbon::now()->setSeconds(0);
                    $nowEnd = Carbon::now()->setSeconds(59);

                    // Check maintenance status
                    $isMaintenance = $startDate->lessThanOrEqualTo($nowStart) && $endDate->greaterThanOrEqualTo($nowEnd);
                }

                // Helper function to format date adjustments
                $formatDateAdjustment = function ($date) {
                    return [
                        'to_minute' => $date ? $date->diffInMinutes($date->copy()->startOfDay()) : '',
                        'y' => $date ? $date->year : '',
                        'm' => $date ? $date->month : '',
                        'd' => $date ? $date->day : '',
                        'h' => $date ? $date->hour : '',
                        'i' => $date ? $date->minute : '',
                        's' => $date ? $date->second : '',
                    ];
                };

                $menuName = $game['menuName'];
                $menuCode = $game['menuCode'];

                $data['menu_name'] = $menuName;
                $data['menu_code'] = $menuCode;

                $data['game'][] = [
                    'id' => $game['providerId'],
                    'name' => $game['providerName'],
                    'provider' => $game['providerCode'],
                    'code' => $game['providerCode'],
                    'category_id' => $game['providerCategoryId'],
                    'category_name' => $game['categoryName'],
                    'category_alias_name' => $game['categoryAliasName'],
                    'provider_maintenances' => $isMaintenance,
                    'provider_maintenances' => $isMaintenance,
                    'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : '',
                    'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : '',
                    'adjust_start_date' => $formatDateAdjustment($startDate),
                    'adjust_end_date' => $formatDateAdjustment($endDate),
                ];
            }
        }

        return response()->json([
            "code" => "200",
            "message" => "Success",
            "data" => $data
        ]);
    }
}
