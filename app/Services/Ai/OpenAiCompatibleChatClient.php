<?php

namespace App\Services\Ai;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * OpenAI-compatible /chat/completions (DeepSeek, PlusVibe, …).
 */
class OpenAiCompatibleChatClient
{
    /** @var Client */
    protected $client;

    /** @var string */
    protected $apiKey;

    /** @var array<string, mixed> */
    protected $lastUsage = [];

    public function __construct(string $apiKey, string $baseUri, int $timeout = 240)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'timeout' => $timeout,
            'connect_timeout' => 20,
        ]);
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        $attempts = 2;
        $last = null;
        for ($try = 1; $try <= $attempts; $try++) {
            try {
                $response = $this->client->post('chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => array_merge([
                        'model' => $model,
                        'messages' => $messages,
                    ], $options),
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                if (! is_array($data)) {
                    throw new \RuntimeException('AI response is not JSON');
                }
                $this->lastUsage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

                return $data;
            } catch (\Throwable $e) {
                $last = $e;
                if ($try >= $attempts || ! $this->isRetryable($e)) {
                    throw $e;
                }
                sleep($try * 3);
            }
        }

        throw $last instanceof \Throwable ? $last : new \RuntimeException('AI request failed');
    }

    public function request(string $prompt, string $model, array $options = []): string
    {
        $result = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $model, $options);

        return (string) ($result['choices'][0]['message']['content'] ?? '');
    }

    public function getLastUsageTokens(): int
    {
        return (int) ($this->lastUsage['total_tokens'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastUsageDetails(): array
    {
        return $this->lastUsage;
    }

    protected function isRetryable(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'cURL error 28') !== false
            || stripos($msg, 'cURL error 52') !== false
            || stripos($msg, 'cURL error 56') !== false
            || stripos($msg, 'timed out') !== false
        ) {
            return true;
        }
        if ($e instanceof RequestException && $e->hasResponse()) {
            $code = (int) $e->getResponse()->getStatusCode();

            return in_array($code, [429, 500, 502, 503, 504], true);
        }

        return false;
    }
}
