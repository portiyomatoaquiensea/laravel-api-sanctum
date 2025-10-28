<?php

namespace App\Auth;

use App\Models\TempUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class TempUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        return session()->has('user') ? new TempUser() : null;
    }

    public function retrieveByToken($identifier, $token) { return null; }

    public function updateRememberToken(Authenticatable $user, $token) {}

    public function retrieveByCredentials(array $credentials) { return null; }

    public function validateCredentials(Authenticatable $user, array $credentials) { return true; }

    // ✅ Updated method signature
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): ?string
    {
        return null; // no password hashing for Temp user
    }

    public function getModel()
    {
        return TempUser::class;
    }
}
