<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiRateLimitService
{
    /**
     * Daily limit for Gemini API requests.
     */
    private const DAILY_REQUEST_LIMIT = 1000;

    /**
     * Cache key for tracking daily requests.
     */
    private const CACHE_KEY = 'gemini_daily_requests';

    /**
     * Cache key for tracking when the limit resets.
     */
    private const RESET_KEY = 'gemini_daily_reset';

    /**
     * Check if we can make a Gemini API request.
     */
    public function canMakeRequest(): bool
    {
        $currentCount = $this->getCurrentRequestCount();

        return $currentCount < self::DAILY_REQUEST_LIMIT;
    }

    /**
     * Increment the daily request counter.
     */
    public function incrementRequestCount(): void
    {
        $currentCount = $this->getCurrentRequestCount();
        $newCount = $currentCount + 1;

        // Cache until next Pacific midnight
        $secondsUntilPacificMidnight = $this->getSecondsUntilPacificMidnight();

        Cache::put(self::CACHE_KEY, $newCount, $secondsUntilPacificMidnight);

        // Set reset time if not already set
        if (! Cache::has(self::RESET_KEY)) {
            Cache::put(self::RESET_KEY, $this->getNextPacificMidnight()->timestamp, $secondsUntilPacificMidnight);
        }

        Log::info('Gemini API request count incremented', [
            'current_count' => $newCount,
            'limit' => self::DAILY_REQUEST_LIMIT,
            'remaining' => self::DAILY_REQUEST_LIMIT - $newCount,
        ]);
    }

    /**
     * Get the current daily request count.
     */
    public function getCurrentRequestCount(): int
    {
        return Cache::get(self::CACHE_KEY, 0);
    }

    /**
     * Get remaining requests for today.
     */
    public function getRemainingRequests(): int
    {
        return max(0, self::DAILY_REQUEST_LIMIT - $this->getCurrentRequestCount());
    }

    /**
     * Get the daily request limit.
     */
    public function getDailyLimit(): int
    {
        return self::DAILY_REQUEST_LIMIT;
    }

    /**
     * Get time until the limit resets (in seconds).
     */
    public function getSecondsUntilReset(): int
    {
        $resetTime = Cache::get(self::RESET_KEY);

        if ($resetTime) {
            return max(0, $resetTime - now()->timestamp);
        }

        return $this->getSecondsUntilPacificMidnight();
    }

    /**
     * Get formatted time until reset.
     */
    public function getTimeUntilReset(): string
    {
        $seconds = $this->getSecondsUntilReset();
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours} hours and {$minutes} minutes";
        }

        return "{$minutes} minutes";
    }

    /**
     * Check if the daily limit has been reached.
     */
    public function isLimitReached(): bool
    {
        return ! $this->canMakeRequest();
    }

    /**
     * Get an informative message about the current rate limit status.
     */
    public function getStatusMessage(): string
    {
        if ($this->isLimitReached()) {
            return "Daily AI moderation limit reached. Chirps cannot be posted or edited until the limit resets at Pacific midnight in {$this->getTimeUntilReset()}.";
        }

        return "AI moderation is available for your chirps.";
    }

    /**
     * Get seconds until next Pacific midnight (when daily limit resets).
     */
    private function getSecondsUntilPacificMidnight(): int
    {
        return now()->diffInSeconds($this->getNextPacificMidnight());
    }

    /**
     * Get the next Pacific midnight.
     */
    private function getNextPacificMidnight(): \Carbon\Carbon
    {
        return now()->setTimezone('America/Los_Angeles')->endOfDay();
    }
}
