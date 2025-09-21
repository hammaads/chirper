<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiRateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_rate_limit_middleware_blocks_chirp_creation(): void
    {
        // Set up rate limit as reached
        Cache::put('gemini_daily_requests', 1000, 3600);
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Verify rate limit is reached
        $rateLimitService = app(GeminiRateLimitService::class);
        $this->assertTrue($rateLimitService->isLimitReached());
        
        // Try to create a chirp - should be blocked
        $response = $this->post('/chirps', [
            'message' => 'This should be blocked'
        ]);
        
        // Should be redirected back with error
        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);
        
        // Verify no chirp was created
        $this->assertDatabaseMissing('chirps', [
            'message' => 'This should be blocked'
        ]);
    }
    
    public function test_gemini_rate_limit_middleware_allows_chirp_creation_when_under_limit(): void
    {
        // Ensure rate limit is not reached
        Cache::put('gemini_daily_requests', 5, 3600);
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Verify rate limit is not reached
        $rateLimitService = app(GeminiRateLimitService::class);
        $this->assertFalse($rateLimitService->isLimitReached());
        
        // Try to create a chirp - should be allowed
        $response = $this->post('/chirps', [
            'message' => 'This should be allowed'
        ]);
        
        // Should redirect to home with success
        $response->assertRedirect('/');
        $response->assertSessionHas('success');
        
        // Verify chirp was created
        $this->assertDatabaseHas('chirps', [
            'message' => 'This should be allowed',
            'user_id' => $user->id
        ]);
    }
}