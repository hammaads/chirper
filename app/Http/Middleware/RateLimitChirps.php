<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitChirps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $userId = $user->id;
        $cacheKey = "chirp_rate_limit_{$userId}";

        // Get current count from cache
        $chirpCount = Cache::get($cacheKey, 0);

        // Rate limit: 10 chirps per hour
        $maxChirpsPerHour = 10;

        if ($chirpCount >= $maxChirpsPerHour) {
            // Get the time when the limit resets
            $resetTime = Cache::get("chirp_rate_limit_reset_{$userId}");
            $timeUntilReset = $resetTime ? $resetTime - time() : 3600;
            $minutesUntilReset = ceil($timeUntilReset / 60);

            return redirect()->back()->withErrors([
                'message' => "You've reached the limit of {$maxChirpsPerHour} chirps per hour. Please wait {$minutesUntilReset} minutes before posting again.",
            ]);
        }

        // Increment the counter
        Cache::put($cacheKey, $chirpCount + 1, 3600); // 1 hour

        // Set reset time if not already set
        if (! Cache::has("chirp_rate_limit_reset_{$userId}")) {
            Cache::put("chirp_rate_limit_reset_{$userId}", time() + 3600, 3600);
        }

        return $next($request);
    }
}
