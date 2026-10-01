<?php

use App\Agent\AgentEvent;
use App\Agent\Llm\AnthropicLlmClient;
use App\Agent\Llm\LlmException;
use App\Agent\Llm\LlmManager;
use App\Agent\Llm\OpenAiCompatibleLlmClient;
use Tests\Support\ScriptedLlmClient as Model;

beforeEach(fn () => $this->llms = app(LlmManager::class));

it('offers nothing until a provider is configured', function () {
    expect($this->llms->available())->toBe([])
        ->and($this->llms->defaultName())->toBe('anthropic')
        ->and(fn () => $this->llms->for('anthropic'))->toThrow(LlmException::class, 'not configured');
});

it('offers a provider once it has a key and a model', function () {
    config([
        'healtrip.llm.providers.anthropic.api_key' => 'sk-ant',
        'healtrip.llm.providers.openai.api_key' => 'sk-openai',
        'healtrip.llm.providers.openai.model' => 'gpt-test',
        // A key without a model is not enough.
        'healtrip.llm.providers.gemini.api_key' => 'g-key',
    ]);

    expect($this->llms->available())->toBe([
        ['name' => 'anthropic', 'label' => 'Claude', 'model' => 'claude-fable-5-1'],
        ['name' => 'openai', 'label' => 'GPT', 'model' => 'gpt-test'],
    ])
        ->and($this->llms->for('anthropic'))->toBeInstanceOf(AnthropicLlmClient::class)
        ->and($this->llms->for('openai'))->toBeInstanceOf(OpenAiCompatibleLlmClient::class)
        ->and($this->llms->for('openai'))->toBe($this->llms->for('openai'));
});

it('never exposes a key in what it offers', function () {
    config(['healtrip.llm.providers.anthropic.api_key' => 'sk-ant-secret']);

    expect(json_encode($this->llms->available()))->not->toContain('sk-ant-secret');
});

it('accepts a local provider that needs no key', function () {
    config(['healtrip.llm.providers.ollama.model' => 'local-model']);

    expect($this->llms->isAvailable('ollama'))->toBeTrue();
});

it('falls back to a configured provider when the default is not', function () {
    config(['healtrip.llm.providers.gemini.api_key' => 'g-key', 'healtrip.llm.providers.gemini.model' => 'gemini-test']);

    expect($this->llms->defaultName())->toBe('gemini');
});

it('refuses a provider it does not know', function () {
    expect(fn () => $this->llms->for('skynet'))->toThrow(LlmException::class, 'not configured');
});

it('runs each conversation on the provider it was started with', function () {
    $claude = scriptProvider('anthropic', Model::says('Claude here.'));
    $gpt = scriptProvider('openai', Model::says('GPT here.'));

    $onClaude = runTurn(conversation(provider: 'anthropic'), 'hello');
    $onGpt = runTurn(conversation(provider: 'openai'), 'hello');

    expect(firstEvent($onClaude, AgentEvent::MESSAGE)->data['text'])->toBe('Claude here.')
        ->and(firstEvent($onGpt, AgentEvent::MESSAGE)->data['text'])->toBe('GPT here.')
        ->and($claude->requests)->toHaveCount(1)
        ->and($gpt->requests)->toHaveCount(1);
});

it('answers safely when a conversation\'s provider is no longer configured', function () {
    $events = runTurn(conversation(provider: 'openai'), 'hello');

    expect(firstEvent($events, AgentEvent::ERROR)->data)->toBe(['code' => 'not_configured', 'retryable' => false])
        ->and(firstEvent($events, AgentEvent::MESSAGE)->data['fallback'])->toBe('unavailable');
});
