<?php

namespace App\Http\Middleware;

use App\Services\GeminiRateLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGeminiRateLimit
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly GeminiRateLimitService $rateLimitService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check rate limit for chirp creation and update operations
        if (! $this->isChirpOperation($request)) {
            return $next($request);
        }

        // Check if we've hit the daily Gemini API limit
        if ($this->rateLimitService->isLimitReached()) {
            return redirect()->back()->withErrors([
                'message' => $this->rateLimitService->getStatusMessage(),
            ])->withInput();
        }

        return $next($request);
    }

    /**
     * Determine if this is a chirp operation that requires moderation.
     */
    private function isChirpOperation(Request $request): bool
    {
        // Check if this is a POST to /chirps (create) or PUT/PATCH to /chirps/{id} (update)
        $route = $request->route();

        if (! $route) {
            return false;
        }

        $routeName = $route->getName();
        $method = $request->method();
        $path = $request->path();

        // Check for chirp store operation
        if ($method === 'POST' && str_starts_with($path, 'chirps')) {
            return true;
        }

        // Check for chirp update operation
        if (in_array($method, ['PUT', 'PATCH']) && str_contains($path, 'chirps/')) {
            return true;
        }

        return false;
    }
}
