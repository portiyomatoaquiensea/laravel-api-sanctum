<?php

namespace App\Services;

use App\Models\Session\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SessionService
{
    private const SESSION_KEY = 'user';
    private const SESSION_PATH = 'framework/sessions';

    public function getUser(Request $request): ?UserSession
    {
        $data = $request->session()->get(self::SESSION_KEY);
        return $data ? UserSession::fromArray($data) : null;
    }

    public function setUser(Request $request, UserSession $user): void
    {
        $request->session()->put(self::SESSION_KEY, $user->toArray());
    }

    public function clearUser(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * List all active users stored in file-based sessions.
     * Returns an array of user info extracted from session files.
     */
    public function listActiveUser(): array
    {
        $sessionDir = storage_path(self::SESSION_PATH);
        $sessions = [];

        foreach (File::files($sessionDir) as $file) {
            $content = File::get($file->getRealPath());

            // Unserialize Laravel session file safely
            $data = @unserialize($content);

            if ($data && isset($data['user'])) {
                $user = $data['user'];
                $sessions[] = [
                    'session_file' => $file->getFilename(),
                    'user_id'      => $user['id'] ?? null,
                    'username'     => $user['username'] ?? null,
                    'user_code'    => $user['customer_code'] ?? null,
                    'company_id'   => $user['company_id'] ?? null,
                    'company_code' => $user['company_code'] ?? null,
                    'last_modified'=> date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        return $sessions;
    }

    /**
     * Kick out a user by deleting their session file(s).
     * Accepts either a user_id (string/UUID) or a session filename.
     * Returns number of sessions deleted.
     */
    public function kickOutUser(int|string $identifier): int
    {
        $sessionDir = storage_path(self::SESSION_PATH);
        $deletedCount = 0;

        foreach (File::files($sessionDir) as $file) {
            $content = File::get($file->getRealPath());
            $data = @unserialize($content);

            // If session contains 'user' data
            if ($data && isset($data['user'])) {
                $user = $data['user'];

                // Match by user_id (string or int)
                if ($user['id'] === $identifier) {
                    File::delete($file->getRealPath());
                    $deletedCount++;
                    continue; // skip to next file
                }
            }

            // Or match by session filename
            if ($file->getFilename() === $identifier) {
                File::delete($file->getRealPath());
                $deletedCount++;
            }
        }

        return $deletedCount; // returns how many sessions were removed
    }
}
