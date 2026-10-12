<?php

use App\Services\Ai\GroqClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('the services config maps GROQ_API_KEY to services.groq.key, matching what GroqClient reads', function () {
    $servicesConfig = file_get_contents(config_path('services.php'));

    // Guards against the config key and the env var name drifting apart —
    // GroqClient reads config('services.groq.key'), which must come from GROQ_API_KEY.
    expect($servicesConfig)->toContain("'key' => env('GROQ_API_KEY')");

    config(['services.groq.key' => 'a-test-key']);

    expect((string) config('services.groq.key'))->toBe('a-test-key');
});

test('a missing api key is never sent to groq and logs a safe boolean warning, no secrets', function () {
    config(['services.groq.key' => '']);

    Http::fake();
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context = []) {
            return str_contains($message, 'not configured')
                && ! str_contains($message, 'GROQ_API_KEY=')
                && $context === [];
        });

    expect(fn () => (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Groq API key is not configured.');

    Http::assertNothingSent();
});

test('a successful groq response returns the reply content', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Here is a Cappuccino for ₹120.']],
            ],
        ], 200),
    ]);

    $reply = (new GroqClient)->chat([['role' => 'user', 'content' => 'What do you recommend?']]);

    expect($reply)->toBe('Here is a Cappuccino for ₹120.');
});

test('the request sent to groq carries the bearer token in the header, not the body', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer a-test-key')
            && ! str_contains(json_encode($request->data()), 'a-test-key');
    });
});

test('a failed http response is logged with the status and a sanitized body, never headers or the key', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response(['error' => 'rate limit exceeded'], 429),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context = []) {
            return $message === 'Groq assistant request failed.'
                && $context['status'] === 429
                && str_contains($context['body'], 'rate limit exceeded')
                && ! array_key_exists('headers', $context)
                && ! str_contains(json_encode($context), 'a-test-key')
                && ! str_contains(json_encode($context), 'Bearer');
        });

    expect(fn () => (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Groq request failed with status 429.');
});

test('an empty groq reply is treated as a failure without exposing internals', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '']]]], 200),
    ]);

    expect(fn () => (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Groq returned an empty response.');
});

test('groq client falls back to configured model and base url when none are passed explicitly', function () {
    config([
        'services.groq.key' => 'a-test-key',
        'services.groq.model' => 'custom-model',
        'services.groq.base_url' => 'https://example.test/v1',
    ]);

    Http::fake([
        'example.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(fn ($request) => $request->url() === 'https://example.test/v1/chat/completions'
        && $request['model'] === 'custom-model');
});

test('the request carries a generous max_tokens budget so structured replies are not truncated', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(fn ($request) => $request['max_tokens'] === 1024);
});

test('a gpt-oss reasoning model gets a low reasoning_effort to stop it exhausting the token budget on chain-of-thought', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    (new GroqClient(model: 'openai/gpt-oss-20b'))->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(fn ($request) => $request['model'] === 'openai/gpt-oss-20b'
        && $request['reasoning_effort'] === 'low');
});

test('a non-reasoning model never receives the reasoning_effort parameter', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    (new GroqClient(model: 'llama-3.1-8b-instant'))->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(fn ($request) => ! array_key_exists('reasoning_effort', $request->data()));
});

test('an empty response from a reasoning model is logged with finish_reason and usage for diagnosis, no secrets', function () {
    config(['services.groq.key' => 'a-test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']],
            'usage' => ['completion_tokens' => 1024, 'completion_tokens_details' => ['reasoning_tokens' => 1024]],
        ], 200),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context = []) {
            return $message === 'Groq returned an empty response.'
                && $context['finish_reason'] === 'length'
                && $context['usage']['completion_tokens_details']['reasoning_tokens'] === 1024
                && ! str_contains(json_encode($context), 'a-test-key')
                && ! str_contains(json_encode($context), 'Bearer');
        });

    expect(fn () => (new GroqClient)->chat([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Groq returned an empty response.');
});
