<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\TempUserProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Auth::provider('temp_drive', function($app, array $config) {
            return new TempUserProvider();
        });
    }

    public function boot()
    {
        try {
            // Fetch allowed SPA domains from cache or DB
            $domains = Cache::remember('allowed_domains', 300, function () {
                // Example: fetch from DB if needed
                // return DB::table('allowed_domains')->pluck('domain')->toArray();

                return [
                    'http://localhost:3000',
                    'https://nuxt3-sanctum-production-f4df.up.railway.app',
                ];
            });

            // Always include default local dev domains
            $defaults = [
                'http://localhost',
                'http://localhost:3000',
                'http://127.0.0.1',
                'http://127.0.0.1:8000',
                'http://[::1]',
                'http://localhost:8000'
            ];

            $domains = array_unique(array_merge($defaults, $domains));

            // Apply to Sanctum & CORS
            Config::set('sanctum.stateful', $domains);
            Config::set('cors.allowed_origins', $domains);
            
            // -----------------------------
            // STRICT ORIGIN CHECKING
            // -----------------------------
            // Listen to all route requests and block disallowed origins

            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Routing\Events\RouteMatched::class,
                function ($event) use ($domains) {
                    $request = $event->request; // Illuminate\Http\Request
                    $origin = $request->getSchemeAndHttpHost();
                    // Block if origin is missing or not in allowed domains
                    if (!$origin || !in_array($origin, $domains)) {
                        abort(response()->json([
                            'message' => 'Access denied: invalid origin.'
                        ], 403));
                    }
                }
            );

        } catch (\Exception $e) {
            // Fallback if DB/cache not ready
            $fallback = ['http://localhost:3000'];
            Config::set('sanctum.stateful', $fallback);
            Config::set('cors.allowed_origins', $fallback);
        }
    }


}
