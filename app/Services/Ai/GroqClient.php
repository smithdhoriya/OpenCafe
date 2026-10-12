<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GroqClient
{
    private readonly string $apiKey;

    private readonly string $model;

    private readonly string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? (string) config('services.groq.key');
        $this->model = $model ?? (string) config('services.groq.model');
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.groq.base_url'), '/');
    }

    /**
     * Models that spend completion tokens on an internal reasoning/chain-of-
     * thought pass before writing their final answer (Groq's `openai/gpt-oss-*`
     * family). A small max_tokens budget can be fully consumed by reasoning,
     * leaving no tokens for the actual reply — see isReasoningModel().
     */
    private const REASONING_MODEL_PREFIXES = ['openai/gpt-oss'];

    /**
     * Send a chat-completion request to Groq and return the assistant's
     * reply text. The API key is only ever used here, server-side — it is
     * never exposed to the browser.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        if ($this->apiKey === '') {
            // Boolean-only: never log the key itself, since there isn't one to log.
            Log::warning('Groq assistant request skipped: GROQ_API_KEY is not configured.');

            throw new RuntimeException('Groq API key is not configured.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.4,
            // Structured, multi-section replies (menu lists, budget totals) run
            // long; 1024 leaves headroom beyond the reasoning budget below.
            'max_tokens' => 1024,
        ];

        if ($this->isReasoningModel()) {
            // Keeps the internal reasoning pass short so it can't eat the whole
            // max_tokens budget and leave zero tokens for the final answer.
            $payload['reasoning_effort'] = 'low';
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            // Log the status and a truncated, body-only snippet for diagnosis.
            // Never log request headers (that's where the Authorization/API key lives).
            Log::warning('Groq assistant request failed.', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]);

            throw new RuntimeException("Groq request failed with status {$response->status()}.");
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            // Safe to log: finish_reason/usage carry no customer or secret data,
            // and are the key signal for diagnosing an empty-content response.
            Log::warning('Groq returned an empty response.', [
                'finish_reason' => $response->json('choices.0.finish_reason'),
                'usage' => $response->json('usage'),
            ]);

            throw new RuntimeException('Groq returned an empty response.');
        }

        return trim($content);
    }

    private function isReasoningModel(): bool
    {
        foreach (self::REASONING_MODEL_PREFIXES as $prefix) {
            if (str_starts_with($this->model, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
