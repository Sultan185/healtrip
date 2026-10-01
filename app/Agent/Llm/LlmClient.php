<?php

namespace App\Agent\Llm;

/**
 * The agent's only dependency on a model provider. The loop, tools and
 * guardrails are written against this interface; a driver per wire format
 * implements it, and tests swap in a scripted one.
 *
 * Everything that crosses this interface is in one canonical shape, so the
 * stored transcript does not depend on the vendor:
 *
 *   - A message is {role, content}. Roles are "user", "assistant" and
 *     "system" (an operator note appended mid-conversation). Content is a
 *     string or a list of blocks.
 *   - Blocks every driver understands: {type: "text", text},
 *     {type: "tool_use", id, name, input} and
 *     {type: "tool_result", tool_use_id, content, is_error}.
 *   - A driver may return other block types of its own (Claude's signed
 *     reasoning blocks, for example). They are stored and handed back to
 *     that same driver untouched; a conversation never changes provider.
 *   - A tool definition is {name, description, input_schema, strict}.
 *
 * The canonical shape is deliberately Anthropic's, the richer of the two
 * formats in use: that driver passes it through, the other translates.
 */
interface LlmClient
{
    /**
     * Ask the model for its next message.
     *
     * @param  string  $system  The frozen system prompt.
     * @param  list<array<string, mixed>>  $tools  Tool definitions in API wire format.
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages  The full transcript.
     * @param  bool  $allowTools  False forces a text answer (used on the last iteration of a turn).
     *
     * @throws LlmException
     */
    public function respond(string $system, array $tools, array $messages, bool $allowTools = true): LlmResponse;
}
