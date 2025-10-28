<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class ManageSessionController extends Controller
{
    protected SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * List all active users from file-based sessions.
     */
    public function listActiveUser(Request $request)
    {
        $sessions = $this->sessionService->listActiveUser();

        return response()->json([
            'code' => Response::HTTP_OK,
            'message' => 'Active user sessions retrieved successfully.',
            'data' => $sessions,
        ], Response::HTTP_OK);
    }

    /**
     * Kick out a user by ID or session filename.
     */
    public function kickOutUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            // $errors = $validator->errors()->all();
            return response()->json([
                "code"    => "422",
                "message" => "Missing required parameters",
                "data"    => null
            ], 422);

        }

        $userId = $request->input('user_id');
        $deleted = $this->sessionService->kickOutUser($userId);

        return response()->json([
            'code' => $deleted ? Response::HTTP_OK : Response::HTTP_NOT_FOUND,
            'message' => $deleted 
                ? 'User session(s) kicked out successfully.' 
                : 'No active session found for this identifier.',
            'success' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }
}
