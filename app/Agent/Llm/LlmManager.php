<?php

namespace App\Agent\Llm;

use InvalidArgumentException;

/**
 * Turns the provider blocks in config/healtrip.php into LlmClient instances.
 *
 * It is the only place that knows which providers exist. The agent asks for
 * a client by name (the one stored on the conversation) and gets back the
 * same interface whichever vendor is behind it.
 */
class LlmManager
{
    /** @var array<string, LlmClient> */
    private array $clients = [];

    /**
     * The provider new conversations use: the configured default, or the
     * first provider that is ready when the default is not.
     */
    public function defaultName(): string
    {
        $default = (string) config('healtrip.llm.default');

        if ($this->isAvailable($default)) {
            return $default;
        }

        return $this->available()[0]['name'] ?? $default;
    }

    /**
     * The providers that are ready to use, as offered to the UI. No secrets.
     *
     * @return list<array{name: string, label: string, model: string}>
     */
    public function available(): array
    {
        $names = array_filter(array_keys($this->providers()), $this->isAvailable(...));

        return array_values(array_map($this->describe(...), $names));
    }

    public function isAvailable(string $name): bool
    {
        $provider = $this->providers()[$name] ?? null;

        if ($provider === null || blank($provider['model'] ?? null)) {
            return false;
        }

        return filled($provider['api_key'] ?? null) || ($provider['requires_key'] ?? true) === false;
    }

    /**
     * @return array{name: string, label: string, model: string}
     */
    public function describe(string $name): array
    {
        $provider = $this->providers()[$name] ?? [];

        return [
            'name' => $name,
            'label' => (string) ($provider['label'] ?? $name),
            'model' => (string) ($provider['model'] ?? ''),
        ];
    }

    /**
     * @throws LlmException When the provider is unknown or not configured.
     */
    public function for(string $name): LlmClient
    {
        if (isset($this->clients[$name])) {
            return $this->clients[$name];
        }

        if (! $this->isAvailable($name)) {
            throw LlmException::notConfigured($name);
        }

        $config = [
            'max_tokens' => (int) config('healtrip.llm.max_tokens'),
            'timeout' => (float) config('healtrip.llm.timeout'),
            'max_retries' => (int) config('healtrip.llm.max_retries'),
            ...$this->providers()[$name],
        ];

        return $this->clients[$name] = match ($config['driver'] ?? null) {
            'anthropic' => new AnthropicLlmClient($config),
            'openai' => new OpenAiCompatibleLlmClient($name, $config),
            default => throw new InvalidArgumentException("Provider [{$name}] has no valid driver."),
        };
    }

    /**
     * Use this client for a provider name instead of building one from
     * config. This is how tests plug in a scripted model.
     */
    public function register(string $name, LlmClient $client): void
    {
        $this->clients[$name] = $client;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function providers(): array
    {
        return (array) config('healtrip.llm.providers');
    }
}
