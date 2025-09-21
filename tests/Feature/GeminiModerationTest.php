<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AIModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiModerationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_gemini_api_approves_clean_content(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'SAFE'],
                            ],
                        ],
                        'safetyRatings' => [],
                    ],
                ],
            ], 200),
        ]);

        $service = new AIModerationService('fake-api-key');
        $result = $service->moderateContent('This is a clean message about Laravel!');

        $this->assertEquals('approved', $result['status']);
        $this->assertStringContainsString('Gemini AI moderation', $result['reason']);
        $this->assertEquals(0.95, $result['confidence']);
    }

    public function test_gemini_api_rejects_unsafe_content(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'UNSAFE Contains inappropriate language'],
                            ],
                        ],
                        'safetyRatings' => [],
                    ],
                ],
            ], 200),
        ]);

        $service = new AIModerationService('fake-api-key');
        $result = $service->moderateContent('This is inappropriate content!');

        $this->assertEquals('rejected', $result['status']);
        $this->assertStringContainsString('flagged by Gemini', $result['reason']);
        $this->assertEquals(0.9, $result['confidence']);
    }

    public function test_gemini_safety_filters_block_content(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'SAFE'],
                            ],
                        ],
                        'safetyRatings' => [
                            [
                                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                                'probability' => 'MEDIUM',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new AIModerationService('fake-api-key');
        $result = $service->moderateContent('Hate speech content');

        $this->assertEquals('rejected', $result['status']);
        $this->assertStringContainsString('safety filters', $result['reason']);
        $this->assertEquals(0.9, $result['confidence']);
    }

    public function test_gemini_api_fallback_on_failure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $service = new AIModerationService('fake-api-key');
        $result = $service->moderateContent('This is a test message');

        // Should fall back to basic moderation
        $this->assertContains($result['status'], ['approved', 'rejected']);
        $this->assertStringContainsString('basic moderation', $result['reason']);
    }

    public function test_rate_limiting_prevents_excessive_chirps(): void
    {
        $user = User::factory()->create();

        // Clear any existing rate limit cache
        Cache::forget("chirp_rate_limit_{$user->id}");
        Cache::forget("chirp_rate_limit_reset_{$user->id}");

        // Create 10 chirps (the limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($user)->post('/chirps', [
                'message' => "Test chirp {$i}",
            ]);
            $response->assertRedirect('/');
        }

        // The 11th chirp should be rate limited
        $response = $this->actingAs($user)->post('/chirps', [
            'message' => 'This should be rate limited',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
        $this->assertStringContainsString('limit of 10 chirps per hour', session('errors')->first('message'));
    }

    public function test_rate_limiting_resets_after_time(): void
    {
        $user = User::factory()->create();

        // Set up rate limit as if user has reached the limit
        Cache::put("chirp_rate_limit_{$user->id}", 10, 1); // 1 second
        Cache::put("chirp_rate_limit_reset_{$user->id}", time() + 1, 1);

        // Should be rate limited
        $response = $this->actingAs($user)->post('/chirps', [
            'message' => 'This should be rate limited',
        ]);
        $response->assertSessionHasErrors('message');

        // Wait for cache to expire
        sleep(2);

        // Should work again
        $response = $this->actingAs($user)->post('/chirps', [
            'message' => 'This should work now',
        ]);
        $response->assertRedirect('/');
    }

    public function test_rate_limiting_shows_informative_message(): void
    {
        $user = User::factory()->create();

        // Set up rate limit
        Cache::put("chirp_rate_limit_{$user->id}", 10, 3600);
        Cache::put("chirp_rate_limit_reset_{$user->id}", time() + 1800, 3600); // 30 minutes

        $response = $this->actingAs($user)->post('/chirps', [
            'message' => 'This should be rate limited',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');

        $errorMessage = session('errors')->first('message');
        $this->assertStringContainsString('limit of 10 chirps per hour', $errorMessage);
        $this->assertStringContainsString('30 minutes', $errorMessage);
    }

    public function test_rate_limiting_does_not_affect_guests(): void
    {
        // Guests should not be affected by rate limiting
        $response = $this->post('/chirps', [
            'message' => 'Guest chirp',
        ]);

        // Should redirect to login instead of being rate limited
        $response->assertRedirect('/login');
    }
}
