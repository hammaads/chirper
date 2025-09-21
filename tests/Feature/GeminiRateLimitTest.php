<?php

namespace Tests\Feature;

use App\Models\Chirp;
use App\Models\User;
use App\Services\GeminiRateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GeminiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private GeminiRateLimitService $rateLimitService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimitService = new GeminiRateLimitService;
        $this->user = User::factory()->create();

        // Clear cache before each test
        Cache::flush();
    }

    public function test_can_make_request_when_under_limit(): void
    {
        $this->assertTrue($this->rateLimitService->canMakeRequest());
        $this->assertEquals(0, $this->rateLimitService->getCurrentRequestCount());
        $this->assertEquals(1000, $this->rateLimitService->getRemainingRequests());
    }

    public function test_increment_request_count(): void
    {
        $this->rateLimitService->incrementRequestCount();

        $this->assertEquals(1, $this->rateLimitService->getCurrentRequestCount());
        $this->assertEquals(999, $this->rateLimitService->getRemainingRequests());
    }

    public function test_cannot_make_request_when_limit_reached(): void
    {
        // Set cache to daily limit
        Cache::put('gemini_daily_requests', 1000, 3600);

        $this->assertFalse($this->rateLimitService->canMakeRequest());
        $this->assertTrue($this->rateLimitService->isLimitReached());
        $this->assertEquals(0, $this->rateLimitService->getRemainingRequests());
    }

    public function test_status_message_when_under_limit(): void
    {
        $message = $this->rateLimitService->getStatusMessage();

        $this->assertStringContainsString('AI moderation is available', $message);
    }

    public function test_status_message_when_limit_reached(): void
    {
        Cache::put('gemini_daily_requests', 1000, 3600);

        $message = $this->rateLimitService->getStatusMessage();

        $this->assertStringContainsString('Daily AI moderation limit reached', $message);
        $this->assertStringContainsString('cannot be posted or edited', $message);
        $this->assertStringContainsString('Pacific midnight', $message);
    }

    public function test_chirp_creation_blocked_when_limit_reached(): void
    {
        // Set Gemini rate limit to reached
        Cache::put('gemini_daily_requests', 1000, 3600);

        $this->actingAs($this->user);

        $response = $this->post('/chirps', [
            'message' => 'This should be blocked due to rate limit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);

        // Verify chirp was not created
        $this->assertDatabaseMissing('chirps', [
            'message' => 'This should be blocked due to rate limit',
        ]);
    }

    public function test_chirp_creation_allowed_when_under_limit(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/chirps', [
            'message' => 'This should be allowed',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        // Verify chirp was created
        $this->assertDatabaseHas('chirps', [
            'message' => 'This should be allowed',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_chirp_update_blocked_when_limit_reached(): void
    {
        // Create a chirp first
        $chirp = Chirp::factory()->create([
            'user_id' => $this->user->id,
            'message' => 'Original message',
        ]);

        // Set Gemini rate limit to reached
        Cache::put('gemini_daily_requests', 1000, 3600);

        $this->actingAs($this->user);

        $response = $this->put("/chirps/{$chirp->id}", [
            'message' => 'Updated message - should be blocked',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);

        // Verify chirp was not updated
        $chirp->refresh();
        $this->assertEquals('Original message', $chirp->message);
    }

    public function test_chirp_update_allowed_when_under_limit(): void
    {
        // Create a chirp first
        $chirp = Chirp::factory()->create([
            'user_id' => $this->user->id,
            'message' => 'Original message',
        ]);

        $this->actingAs($this->user);

        $response = $this->put("/chirps/{$chirp->id}", [
            'message' => 'Updated message - should be allowed',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        // Verify chirp was updated
        $chirp->refresh();
        $this->assertEquals('Updated message - should be allowed', $chirp->message);
    }

    public function test_middleware_does_not_block_non_chirp_routes(): void
    {
        // Set Gemini rate limit to reached
        Cache::put('gemini_daily_requests', 1000, 3600);

        $this->actingAs($this->user);

        // Test that other routes are not blocked
        $response = $this->get('/');
        $response->assertOk();
    }

    public function test_rate_limit_resets_after_time(): void
    {
        // Set a past reset time to simulate reset
        Cache::put('gemini_daily_requests', 1000, 1); // Very short TTL
        Cache::put('gemini_daily_reset', now()->subDay()->timestamp, 1);

        // Wait for cache to expire
        sleep(2);

        $this->assertTrue($this->rateLimitService->canMakeRequest());
        $this->assertEquals(0, $this->rateLimitService->getCurrentRequestCount());
    }

    public function test_get_time_until_reset_formatting(): void
    {
        // Set reset time to 2 hours and 30 minutes from now
        Cache::put('gemini_daily_reset', now()->addHours(2)->addMinutes(30)->timestamp, 3600);

        $timeString = $this->rateLimitService->getTimeUntilReset();

        $this->assertStringContainsString('2 hours', $timeString);
        $this->assertStringContainsString('30 minutes', $timeString);
    }

    public function test_get_time_until_reset_minutes_only(): void
    {
        // Set reset time to 45 minutes from now
        Cache::put('gemini_daily_reset', now()->addMinutes(45)->timestamp, 3600);

        $timeString = $this->rateLimitService->getTimeUntilReset();

        $this->assertStringContainsString('45 minutes', $timeString);
        $this->assertStringNotContainsString('hours', $timeString);
    }

    public function test_pacific_time_reset_behavior(): void
    {
        // Test that the service uses Pacific time for reset calculations
        $service = app(GeminiRateLimitService::class);
        
        // Get the next Pacific midnight
        $pacificMidnight = now()->setTimezone('America/Los_Angeles')->endOfDay();
        $secondsUntilPacificMidnight = now()->diffInSeconds($pacificMidnight);
        
        // Verify the service calculates the same time
        $serviceSeconds = $service->getSecondsUntilReset();
        
        // Allow for small differences due to timing
        $this->assertLessThan(5, abs($secondsUntilPacificMidnight - $serviceSeconds));
    }
}
