<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chirp>
 */
class ChirpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'message' => $this->faker->sentence(),
            'moderation_status' => 'pending',
            'moderation_reason' => null,
            'moderated_at' => null,
        ];
    }

    /**
     * Indicate that the chirp is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'approved',
            'moderation_reason' => 'Content passed AI moderation',
            'moderated_at' => now(),
        ]);
    }

    /**
     * Indicate that the chirp is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'rejected',
            'moderation_reason' => 'Content flagged for inappropriate language',
            'moderated_at' => now(),
        ]);
    }

    /**
     * Indicate that the chirp is pending moderation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'pending',
            'moderation_reason' => null,
            'moderated_at' => null,
        ]);
    }
}
