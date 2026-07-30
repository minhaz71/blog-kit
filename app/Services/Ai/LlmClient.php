<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin multi-provider chat client. Each provider has its own endpoint,
 * auth scheme, and payload shape; the rest of the agent only ever calls
 * complete(system, user) and receives plain text back.
 *
 * Security: API keys travel in headers only (never in URLs, so they can't
 * leak into logs, proxies, or error messages). Truncated/blocked responses
 * throw with actionable messages instead of returning cut-off JSON.
 */
class LlmClient
{
    protected string $purpose = 'write';

    protected ?int $batchId = null;

    protected ?int $itemId = null;

    public function __construct(
        protected string $provider,
        protected string $apiKey,
        protected string $model,
    ) {}

    /** Attach batch/item context so every request logs to the usage dashboard. */
    public function withContext(string $purpose, ?int $batchId = null, ?int $itemId = null): self
    {
        $this->purpose = $purpose;
        $this->batchId = $batchId;
        $this->itemId = $itemId;

        return $this;
    }

    protected function recordUsage(int $input, int $output, int $cached = 0, int $cacheWrite = 0): void
    {
        try {
            \App\Models\AiUsageLog::record(
                $this->provider, $this->model, $input, $output, $cached,
                $this->purpose, $this->batchId, $this->itemId, $cacheWrite,
            );
        } catch (\Throwable) {
            // Usage accounting must never break the pipeline.
        }
    }

    public static function for(string $provider, ?string $model = null): self
    {
        $apiKey = (string) setting("ai.{$provider}_api_key");

        if ($apiKey === '') {
            throw new RuntimeException("No API key configured for {$provider}. Add it in Settings → AI settings.");
        }

        $model = $model ?: (string) setting("ai.{$provider}_model") ?: self::defaultModel($provider);

        return new self($provider, $apiKey, $model);
    }

    public static function defaultModel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'gpt-4o-mini',
            'anthropic' => 'claude-sonnet-5',
            'gemini' => 'gemini-2.0-flash',
            default => throw new RuntimeException("Unknown provider {$provider}"),
        };
    }

    /**
     * Send a system+user prompt, return the assistant text.
     *
     * cacheStatic: mark the system block as provider-cacheable. The batch
     * pipeline keeps the system prompt byte-identical across items, so
     * Anthropic serves it from cache (90% cheaper) and OpenAI/Gemini hit
     * their automatic prefix caches — the big instruction block is
     * effectively paid for once per batch, not once per product.
     */
    public function complete(string $system, string $user, int $maxTokens = 4096, bool $cacheStatic = false): string
    {
        $startedAt = microtime(true);

        // Clamp to what the provider actually allows so a high request never
        // errors (Gemini caps at 8192) and a low one isn't left too small.
        $maxTokens = min($maxTokens, $this->maxOutputCap());

        Log::channel('ai')->info("[{$this->provider}/{$this->model}] {$this->purpose} request", [
            'batch_id' => $this->batchId,
            'item_id' => $this->itemId,
            'system_chars' => mb_strlen($system),
            'user_chars' => mb_strlen($user),
            'max_tokens' => $maxTokens,
        ]);

        try {
            $text = match ($this->provider) {
                'openai' => $this->openai($system, $user, $maxTokens),
                'anthropic' => $this->anthropic($system, $user, $maxTokens, $cacheStatic),
                'gemini' => $this->gemini($system, $user, $maxTokens),
                default => throw new RuntimeException("Unknown provider {$this->provider}"),
            };

            Log::channel('ai')->info("[{$this->provider}/{$this->model}] {$this->purpose} OK", [
                'batch_id' => $this->batchId,
                'item_id' => $this->itemId,
                'ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'reply_chars' => mb_strlen($text),
                'reply_preview' => mb_substr($text, 0, 200),
            ]);

            return $text;
        } catch (\Throwable $e) {
            Log::channel('ai')->error("[{$this->provider}/{$this->model}] {$this->purpose} FAILED: {$e->getMessage()}", [
                'batch_id' => $this->batchId,
                'item_id' => $this->itemId,
                'ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        }
    }

    /**
     * Execute a provider request and translate failures into readable
     * messages: the provider's own error text, not "HTTP request failed".
     * Retries transient HTTP errors AND connection failures (DNS blips,
     * timeouts), honouring the provider's Retry-After header when sent.
     */
    protected function request(callable $call, int $attempt = 1): \Illuminate\Http\Client\Response
    {
        try {
            return $call()->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Overload / rate-limit: back off and retry up to 3 attempts
            // before failing the product.
            if (in_array($e->response->status(), [429, 500, 502, 503, 504, 529]) && $attempt < 3) {
                $retryAfter = (int) $e->response->header('retry-after');
                $delay = $retryAfter > 0 ? min($retryAfter, 30) : 3 * $attempt;
                Log::channel('ai')->warning("[{$this->provider}/{$this->model}] HTTP {$e->response->status()} — retrying in {$delay}s (attempt {$attempt}/3)");

                if (! app()->runningUnitTests()) {
                    sleep($delay);
                }

                return $this->request($call, $attempt + 1);
            }

            Log::channel('ai')->error("[{$this->provider}/{$this->model}] HTTP {$e->response->status()} raw response", [
                'request_id' => $e->response->header('request-id'),
                'body' => mb_substr($e->response->body(), 0, 4000),
            ]);

            $body = $e->response->json();
            $providerMessage = $body['error']['message']        // OpenAI, Anthropic, Gemini all use error.message
                ?? $body['error']['type']
                ?? mb_substr($e->response->body(), 0, 300);
            $status = $e->response->status();

            $hint = match (true) {
                $status === 401 => ' — the API key is invalid or revoked. Check Settings → AI settings.',
                $status === 403 => ' — the API key lacks permission for this model/endpoint.',
                $status === 404 => " — model \"{$this->model}\" not found for this provider. Check the model name.",
                $status === 429 => ' — rate limit or quota exceeded. Wait a moment or check your provider billing.',
                $status >= 500 => ' — provider outage; retry shortly.',
                default => '',
            };

            throw new RuntimeException(ucfirst($this->provider)." API error (HTTP {$status}): {$providerMessage}{$hint}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Transient network failure (DNS, TLS, timeout) — retry too.
            if ($attempt < 3) {
                Log::channel('ai')->warning("[{$this->provider}/{$this->model}] connection error — retrying (attempt {$attempt}/3)");

                if (! app()->runningUnitTests()) {
                    sleep(3 * $attempt);
                }

                return $this->request($call, $attempt + 1);
            }

            throw new RuntimeException(ucfirst($this->provider).' unreachable after 3 attempts — check your server\'s internet connection and DNS.');
        }
    }

    /** Tiny live request to verify key + model + endpoint. Returns [ok, message, ms]. */
    public function healthCheck(): array
    {
        $startedAt = microtime(true);

        try {
            $reply = $this->complete('Reply with exactly: OK', 'ping', 512);
            $ms = (int) round((microtime(true) - $startedAt) * 1000);

            return [true, "{$this->model} responded in {$ms}ms".(str_contains($reply, 'OK') ? '' : ' (unexpected reply)'), $ms];
        } catch (\Throwable $e) {
            return [false, $e->getMessage(), (int) round((microtime(true) - $startedAt) * 1000)];
        }
    }

    /** o-series reasoning models reject max_tokens; they require max_completion_tokens. */
    protected function usesCompletionTokensParam(): bool
    {
        return (bool) preg_match('/^o\d/', $this->model);
    }

    /** Provider/model ceiling for output tokens, so we never over-request. */
    protected function maxOutputCap(): int
    {
        return match ($this->provider) {
            // Claude 4.x/5 support very large output; keep generous headroom.
            'anthropic' => 32000,
            // gpt-4o family: 16384; o-series reasoning models: much higher.
            'openai' => $this->usesCompletionTokensParam() ? 32000 : 16384,
            // Gemini 2.0 Flash hard-caps at 8192; 2.5 supports more but 8192 is safe.
            'gemini' => str_contains($this->model, '2.5') ? 16000 : 8192,
            default => 8192,
        };
    }

    protected function openai(string $system, string $user, int $maxTokens): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];

        // o3 / o4-mini etc. 400 on max_tokens — they take max_completion_tokens
        // and need extra headroom for hidden reasoning tokens.
        if ($this->usesCompletionTokensParam()) {
            $payload['max_completion_tokens'] = $maxTokens * 2;
        } else {
            $payload['max_tokens'] = $maxTokens;
        }

        $response = $this->request(fn () => Http::withToken($this->apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', $payload))->json();

        $this->recordUsage(
            (int) ($response['usage']['prompt_tokens'] ?? 0),
            (int) ($response['usage']['completion_tokens'] ?? 0),
            (int) ($response['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
        );

        // Truncation must throw, not silently return cut-off JSON.
        $finishReason = $response['choices'][0]['finish_reason'] ?? null;

        if ($finishReason === 'length') {
            throw new RuntimeException(
                "GPT output was cut off (finish_reason: length) — the JSON is incomplete. Retry, or reduce allowed sections/description length."
            );
        }

        if ($finishReason === 'content_filter') {
            throw new RuntimeException(
                'GPT refused this content (finish_reason: content_filter). Rephrase the source data or use a different provider.'
            );
        }

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    protected function anthropic(string $system, string $user, int $maxTokens, bool $cacheStatic = false): string
    {
        $raw = $this->request(fn () => Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $cacheStatic
                    ? [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]]
                    : $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]));

        // request-id is what Anthropic support asks for — always keep it.
        Log::channel('ai')->debug("[anthropic/{$this->model}] request-id: ".$raw->header('request-id'));

        $response = $raw->json();

        $usage = $response['usage'] ?? [];
        $this->recordUsage(
            (int) ($usage['input_tokens'] ?? 0)
                + (int) ($usage['cache_creation_input_tokens'] ?? 0)
                + (int) ($usage['cache_read_input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            (int) ($usage['cache_read_input_tokens'] ?? 0),
            (int) ($usage['cache_creation_input_tokens'] ?? 0),
        );

        // Content may hold thinking blocks before the text — take all text blocks.
        $text = collect($response['content'] ?? [])
            ->filter(fn ($block) => ($block['type'] ?? 'text') === 'text')
            ->pluck('text')
            ->implode('');

        $stopReason = $response['stop_reason'] ?? null;

        if ($stopReason === 'refusal') {
            $details = $response['stop_details'] ?? [];
            throw new RuntimeException(
                'Claude refused this request (category: '.($details['category'] ?? 'unspecified').')'
                .(! empty($details['explanation']) ? ' — '.$details['explanation'] : '')
                .' Rephrase the source data or system prompt, or try a different model. request-id: '.$raw->header('request-id')
            );
        }

        if ($stopReason === 'max_tokens') {
            throw new RuntimeException(
                'Claude output was cut off (stop_reason: max_tokens) — the JSON is incomplete. Retry, or reduce allowed sections/description length.'
            );
        }

        if ($stopReason === 'pause_turn') {
            throw new RuntimeException(
                'Claude paused a long-running turn (stop_reason: pause_turn) — retry this product.'
            );
        }

        if ($text === '') {
            Log::channel('ai')->error("[anthropic/{$this->model}] empty response", [
                'stop_reason' => $stopReason,
                'request_id' => $raw->header('request-id'),
                'raw' => mb_substr(json_encode($response), 0, 4000),
            ]);

            throw new RuntimeException(
                "Claude returned no text (stop_reason: ".($stopReason ?? 'unknown')."). Full response saved to storage/logs/ai.log. request-id: ".$raw->header('request-id')
            );
        }

        return $text;
    }

    protected function gemini(string $system, string $user, int $maxTokens): string
    {
        // Key in the header, NOT the URL — URLs leak into logs and error text.
        $response = $this->request(fn () => Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => ['maxOutputTokens' => $maxTokens],
            ]))->json();

        $meta = $response['usageMetadata'] ?? [];
        $this->recordUsage(
            (int) ($meta['promptTokenCount'] ?? 0),
            (int) ($meta['candidatesTokenCount'] ?? 0),
            (int) ($meta['cachedContentTokenCount'] ?? 0),
        );

        // Gemini can return HTTP 200 with NO usable text — diagnose why.
        if ($blockReason = $response['promptFeedback']['blockReason'] ?? null) {
            throw new RuntimeException(
                "Gemini blocked this prompt (blockReason: {$blockReason}). "
                .'Gemini often refuses tobacco/vape/nicotine and similar restricted-product content — '
                .'for this catalog use Claude (Anthropic) or GPT (OpenAI) instead.'
            );
        }

        $candidate = $response['candidates'][0] ?? null;
        $text = (string) ($candidate['content']['parts'][0]['text'] ?? '');
        $finishReason = $candidate['finishReason'] ?? null;

        // Truncated output is unusable even when partial text came back.
        if ($finishReason === 'MAX_TOKENS') {
            throw new RuntimeException(
                'Gemini ran out of output tokens before finishing (finishReason: MAX_TOKENS) — the JSON is incomplete. Retry, or use a shorter prompt/fewer allowed sections.'
            );
        }

        if ($text === '') {
            $finishReason ??= 'NO_CANDIDATES';
            Log::channel('ai')->error("[gemini/{$this->model}] empty response", [
                'finish_reason' => $finishReason,
                'raw' => mb_substr(json_encode($response), 0, 4000),
            ]);

            throw new RuntimeException(match ($finishReason) {
                'SAFETY' => 'Gemini refused to write this content (finishReason: SAFETY). '
                    .'Restricted-product topics (tobacco, vape, nicotine…) are often blocked by Gemini — use Claude or GPT for this catalog.',
                'RECITATION' => 'Gemini stopped for copyright recitation concerns (finishReason: RECITATION). Rephrase the source data and retry.',
                default => "Gemini returned an empty response (finishReason: {$finishReason}). Full response saved to storage/logs/ai.log.",
            });
        }

        return $text;
    }

    /**
     * Extract a JSON object from an LLM reply. Tolerates ``` fences, prose
     * around the object, literal control characters inside string values
     * (the most common LLM JSON fault in long HTML copy) and trailing commas.
     */
    public static function parseJson(string $text): array
    {
        $original = $text;
        $text = trim($text);

        // Prefer the body of a fenced code block when one is present
        // (greedy: runs to the LAST fence, so embedded backticks survive).
        if (preg_match('/```(?:json)?\s*(.*)```/is', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            // Grab the outermost {...} block (drops prose before/after).
            if (preg_match('/\{.*\}/s', $text, $m)) {
                $decoded = json_decode($m[0], true)
                    ?? json_decode(self::repairJson($m[0]), true);
            }
        }

        if (! is_array($decoded)) {
            // Keep the complete reply — a 200-char preview is not enough to
            // diagnose why a specific response failed to parse.
            Log::channel('ai')->error('parseJson failed — full raw reply follows', ['raw' => $original]);

            throw new RuntimeException('LLM did not return valid JSON (full reply in storage/logs/ai.log): '.mb_substr($text, 0, 200));
        }

        return $decoded;
    }

    /**
     * Repair the two JSON faults LLMs actually produce: raw control
     * characters inside string literals (literal newlines/tabs in long HTML
     * values) and trailing commas before } or ].
     */
    protected static function repairJson(string $json): string
    {
        $out = '';
        $inString = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];

            if ($inString) {
                if ($ch === '\\') {         // keep escape pairs intact
                    $out .= $ch.($json[++$i] ?? '');
                } elseif ($ch === '"') {
                    $inString = false;
                    $out .= $ch;
                } elseif ($ch === "\n") {
                    $out .= '\n';
                } elseif ($ch === "\r") {
                    $out .= '\r';
                } elseif ($ch === "\t") {
                    $out .= '\t';
                } else {
                    $out .= $ch;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;
            }
            $out .= $ch;
        }

        return preg_replace('/,\s*([}\]])/', '$1', $out);
    }
}
