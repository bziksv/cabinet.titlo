<?php

namespace App\Services\deepseek;

use App\Services\Ai\OpenAiCompatibleChatClient;

/**
 * Прямой DeepSeek API. Новые модули предпочтительно через AiChatGateway.
 */
class DeepSeekBaseService
{
    /** @var OpenAiCompatibleChatClient */
    protected $client;

    public function __construct()
    {
        $token = (string) config('deepseek.token', '');
        $base = (string) config('deepseek.base_uri', 'https://api.deepseek.com');
        $this->client = new OpenAiCompatibleChatClient($token, $base);
    }

    public function chat(array $messages, string $model = null, array $options = [])
    {
        $model = $model ?: (string) config('deepseek.model', 'deepseek-chat');

        return $this->client->chat($messages, $model, $options);
    }

    public function getLastUsageTokens(): int
    {
        return $this->client->getLastUsageTokens();
    }

    public function getLastUsageDetails(): array
    {
        return $this->client->getLastUsageDetails();
    }

    public function request(string $prompt): string
    {
        return $this->client->request(
            $prompt,
            (string) config('deepseek.model', 'deepseek-chat')
        );
    }
}
