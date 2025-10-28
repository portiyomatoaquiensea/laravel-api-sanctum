<?php
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDO;

class DailyWinloseService
{
    public function __construct()
    {}

    public function totalTurnover(
        string $code,
        string $type,
        string $startDate,
        int $daysToAdd
    )
    {
        if (empty($code) || empty($type) || empty($startDate)) {
            return null;
        }

        // Convert start date to Y-m-d
        $startDateObj = new \DateTime($startDate);
        $endDateObj = (clone $startDateObj)->modify("+{$daysToAdd} days");

        $startDateFormatted = $startDateObj->format('Y-m-d');
        $endDateFormatted = $endDateObj->format('Y-m-d');

        $query = "
            SELECT SUM(turnover) AS totalTurnover
            FROM daily_winloses WITH (NOLOCK)
            WHERE code = :code
            AND type = :type
            AND created >= :start_date
            AND created <= :end_date
        ";

        $stmt = DB::connection('main')->getPdo()->prepare($query);
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $startDateFormatted, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDateFormatted, PDO::PARAM_STR);

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['totalTurnover'] ?? 0;
    }

}