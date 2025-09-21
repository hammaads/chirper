<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIModerationService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta'
    ) {
        // The API key is set via constructor parameter or config
    }

    /**
     * Moderate content using AI service.
     */
    public function moderateContent(string $content): array
    {
        $apiKey = $this->apiKey ?: config('services.gemini.api_key', '');

        if (empty($apiKey)) {
            return $this->fallbackModeration($content);
        }

        try {
            $response = Http::timeout(30)->post($this->baseUrl.'/models/gemini-2.5-flash-lite:generateContent?key='.$apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Please analyze this content for safety and appropriateness. Respond with only 'SAFE' if the content is appropriate, or 'UNSAFE' followed by the reason if it contains inappropriate content. Content to analyze: {$content}",
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 50,
                ],
                'safetySettings' => [
                    [
                        'category' => 'HARM_CATEGORY_HARASSMENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                    ],
                    [
                        'category' => 'HARM_CATEGORY_HATE_SPEECH',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                    ],
                    [
                        'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                    ],
                    [
                        'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->processGeminiResponse($data, $content);
            }

            Log::warning('Gemini API failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return $this->fallbackModeration($content);
        } catch (\Exception $e) {
            Log::error('Gemini moderation service error', [
                'error' => $e->getMessage(),
                'content' => substr($content, 0, 100),
            ]);

            return $this->fallbackModeration($content);
        }
    }

    /**
     * Process Gemini API response.
     */
    private function processGeminiResponse(array $data, string $content): array
    {
        $candidates = $data['candidates'][0] ?? [];
        $text = $candidates['content']['parts'][0]['text'] ?? '';

        // Check for safety issues in the response
        $safetyRatings = $candidates['safetyRatings'] ?? [];
        $blocked = false;
        $safetyReasons = [];

        foreach ($safetyRatings as $rating) {
            if (in_array($rating['probability'], ['MEDIUM', 'HIGH'])) {
                $blocked = true;
                $safetyReasons[] = $rating['category'];
            }
        }

        // If Gemini blocked the response due to safety, the content is unsafe
        if ($blocked) {
            return [
                'status' => 'rejected',
                'reason' => 'Content flagged by Gemini safety filters: '.implode(', ', $safetyReasons),
                'confidence' => 0.9,
            ];
        }

        // Parse the text response
        $text = trim(strtoupper($text));

        if (str_starts_with($text, 'SAFE')) {
            return [
                'status' => 'approved',
                'reason' => 'Content passed Gemini AI moderation',
                'confidence' => 0.95,
            ];
        }

        if (str_starts_with($text, 'UNSAFE')) {
            $reason = trim(substr($text, 6)); // Remove "UNSAFE" prefix

            return [
                'status' => 'rejected',
                'reason' => 'Content flagged by Gemini: '.($reason ?: 'Inappropriate content detected'),
                'confidence' => 0.9,
            ];
        }

        // If we can't parse the response, fall back to basic moderation
        return $this->fallbackModeration($content);
    }

    /**
     * Fallback moderation using simple rules.
     */
    private function fallbackModeration(string $content): array
    {
        $bannedWords = [
            'spam', 'scam', 'fake', 'hate', 'violence', 'harassment',
            'inappropriate', 'offensive', 'abusive', 'threat', 'dangerous',
        ];

        $contentLower = strtolower($content);

        foreach ($bannedWords as $word) {
            if (str_contains($contentLower, $word)) {
                return [
                    'status' => 'rejected',
                    'reason' => "Content contains potentially inappropriate language: {$word}",
                    'confidence' => 0.8,
                ];
            }
        }

        // Check for excessive caps (potential spam)
        $capsRatio = strlen(preg_replace('/[^A-Z]/', '', $content)) / strlen($content);
        if ($capsRatio > 0.7 && strlen($content) > 10) {
            return [
                'status' => 'rejected',
                'reason' => 'Content appears to be spam (excessive capitalization)',
                'confidence' => 0.6,
            ];
        }

        // Check for excessive repetition
        $words = explode(' ', $content);
        $wordCounts = array_count_values($words);
        $maxRepetition = max($wordCounts);
        if ($maxRepetition > 3 && count($words) > 5) {
            return [
                'status' => 'rejected',
                'reason' => 'Content appears to be spam (excessive word repetition)',
                'confidence' => 0.7,
            ];
        }

        return [
            'status' => 'approved',
            'reason' => 'Content passed basic moderation rules',
            'confidence' => 0.9,
        ];
    }
}
