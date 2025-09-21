<?php

namespace App\Jobs;

use App\Models\Chirp;
use App\Services\AIModerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ModerateChirp implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Chirp $chirp
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AIModerationService $moderationService): void
    {
        try {
            Log::info('Starting AI moderation for chirp', [
                'chirp_id' => $this->chirp->id,
                'user_id' => $this->chirp->user_id,
            ]);

            $result = $moderationService->moderateContent($this->chirp->message);

            $this->chirp->update([
                'moderation_status' => $result['status'],
                'moderation_reason' => $result['reason'],
                'moderated_at' => now(),
            ]);

            Log::info('AI moderation completed', [
                'chirp_id' => $this->chirp->id,
                'status' => $result['status'],
                'reason' => $result['reason'],
                'confidence' => $result['confidence'] ?? null,
            ]);

            // If the chirp was rejected, we could send a notification to the user
            if ($result['status'] === 'rejected') {
                $this->handleRejectedChirp();
            }
        } catch (\Exception $e) {
            Log::error('AI moderation job failed', [
                'chirp_id' => $this->chirp->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as approved if moderation fails to avoid blocking content
            $this->chirp->update([
                'moderation_status' => 'approved',
                'moderation_reason' => 'Moderation failed - approved by default',
                'moderated_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a rejected chirp.
     */
    private function handleRejectedChirp(): void
    {
        // Here you could:
        // - Send a notification to the user
        // - Log the rejection for review
        // - Update user reputation/trust score
        // - Send to human moderators for review

        Log::warning('Chirp rejected by AI moderation', [
            'chirp_id' => $this->chirp->id,
            'user_id' => $this->chirp->user_id,
            'message_preview' => substr($this->chirp->message, 0, 50),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ModerateChirp job failed permanently', [
            'chirp_id' => $this->chirp->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark as approved if job fails permanently
        $this->chirp->update([
            'moderation_status' => 'approved',
            'moderation_reason' => 'Moderation job failed - approved by default',
            'moderated_at' => now(),
        ]);
    }
}
