<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AllowedDomain extends Model
{
    protected static function booted()
    {
        static::saved(fn() => Cache::forget('allowed_domains'));
        static::deleted(fn() => Cache::forget('allowed_domains'));
    }
}
