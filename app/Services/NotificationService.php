<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use PDO;

class NotificationService
{
    public function __construct()
    {}

    public function update(object $data, string $type)
    {
        // Validate inputs
        if (!$data || !$type) {
            return false;
        }

        $field = $type . '_count';

        try {
            // Fetch the first notification matching group_id and company_id
            $notifyData = DB::connection('main')->selectOne(
                'SELECT TOP 1 * FROM notifications WHERE group_id = :group_id AND company_id = :company_id',
                [
                    'group_id'   => $data->group_id,
                    'company_id' => $data->company_id,
                ]
            );

            // Determine new count
            $count = ($notifyData->{$field} ?? 0) + 1;

            if ($notifyData) {
                // Update existing record
                DB::connection('main')->update(
                    "UPDATE notifications 
                    SET $field = :count 
                    WHERE group_id = :group_id AND company_id = :company_id",
                    [
                        'count'      => $count,
                        'group_id'   => $data->group_id,
                        'company_id' => $data->company_id,
                    ]
                );
            } else {
                // Insert new record
                DB::connection('main')->insert(
                    "INSERT INTO notifications (group_id, company_id, $field) 
                    VALUES (:group_id, :company_id, :count)",
                    [
                        'group_id'   => $data->group_id,
                        'company_id' => $data->company_id,
                        'count'      => $count,
                    ]
                );
            }

            return true; // Success
        } catch (Exception $e) {
            // Log the error for debugging
            \Log::error("Failed to update notification: " . $e->getMessage(), [
                'group_id' => $data->group_id ?? null,
                'company_id' => $data->company_id ?? null,
                'type' => $type,
            ]);

            return false; // Failure
        }
    }
}