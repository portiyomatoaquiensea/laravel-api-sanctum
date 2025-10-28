<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;

class TempUser implements Authenticatable
{
    public $id;
    public $name;
    public $email;
    public $balance;
    public $point;
    public $bonus;

    public function __construct(array $attributes = [])
    {
        $this->id = $attributes['id'] ?? 1;
        $this->name = $attributes['name'] ?? 'JOKER DIGITAL';
        $this->email = $attributes['email'] ?? 'nara.sukadigital@gmail.com';
        $this->balance = $attributes['balance'] ?? 0.00;
        $this->point = $attributes['point'] ?? 0.00;
        $this->bonus = $attributes['bonus'] ?? 0.00;
    }

    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword() { return ''; }
    public function getRememberToken() {}
    public function setRememberToken($value) {}
    public function getRememberTokenName() {}
}
