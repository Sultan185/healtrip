<?php

namespace App\Agent\Llm;

final readonly class LlmResponse
{
    /**
     * @param  string  $stopReason  end_turn, tool_use, max_tokens, refusal, ...
     * @param  list<array<string, mixed>>  $content  Content blocks exactly as the API returned them.
     *                                               They are stored and replayed verbatim: thinking
     *                                               blocks carry a signature that is invalidated if
     *                                               the block, or anything before it, is altered.
     * @param  array<string, int>  $usage
     */
    public function __construct(
        public string $stopReason,
        public array $content,
        public array $usage = [],
        public string $model = '',
    ) {}

    public function isRefusal(): bool
    {
        return $this->stopReason === 'refusal';
    }

    public function text(): string
    {
        $parts = [];

        foreach ($this->content as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * @return list<array{id: string, name: string, input: array<string, mixed>}>
     */
    public function toolUses(): array
    {
        $uses = [];

        foreach ($this->content as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                $uses[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'name' => (string) ($block['name'] ?? ''),
                    'input' => is_array($block['input'] ?? null) ? $block['input'] : [],
                ];
            }
        }

        return $uses;
    }
}
