<?php

namespace App\Services\Ai;

use App\Support\AiProviderSettingsRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Выбор LLM-провайдера по модулю + fallback модели для PlusVibe.
 */
class AiChatGateway
{
    public const MODULE_AI_GENERATION = 'ai_generation';

    public const MODULE_SITE_AUDIT_ACTION_PLAN = 'site_audit_action_plan';

    /** @var array<string, mixed> */
    protected $lastUsage = [];

    /** @var string|null */
    protected $lastProvider;

    /** @var string|null */
    protected $lastModel;

    public function hasConfiguredProvider(string $module): bool
    {
        $provider = AiProviderSettingsRegistry::providerForModule($module);
        $token = AiProviderSettingsRegistry::tokenForProvider($provider);

        return $token !== null && $token !== '';
    }

    public function request(string $prompt, string $module = self::MODULE_AI_GENERATION, array $options = []): string
    {
        $data = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $module, $options);

        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, string $module = self::MODULE_AI_GENERATION, array $options = []): array
    {
        $provider = AiProviderSettingsRegistry::providerForModule($module);
        $token = AiProviderSettingsRegistry::tokenForProvider($provider);
        if ($token === null || $token === '') {
            throw new \RuntimeException('AI provider token is not configured: ' . $provider);
        }

        $baseUri = AiProviderSettingsRegistry::baseUriForProvider($provider);
        $models = AiProviderSettingsRegistry::modelsForProvider($provider);
        $client = new OpenAiCompatibleChatClient($token, $baseUri);

        $last = null;
        foreach ($models as $model) {
            try {
                $data = $client->chat($messages, $model, $options);
                $this->lastUsage = $client->getLastUsageDetails();
                $this->lastProvider = $provider;
                $this->lastModel = $model;

                return $data;
            } catch (\Throwable $e) {
                $last = $e;
                Log::warning('AI chat attempt failed', [
                    'module' => $module,
                    'provider' => $provider,
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw $last instanceof \Throwable
            ? $last
            : new \RuntimeException('AI chat failed for module ' . $module);
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

    public function getLastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function getLastModel(): ?string
    {
        return $this->lastModel;
    }
}
