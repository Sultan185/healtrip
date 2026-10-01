<?php

namespace App\Agent\Tools;

interface Tool
{
    public function name(): string;

    /**
     * The tool definition in API wire format. It must be deterministic: tool
     * definitions are part of the prompt prefix that is cached and that the
     * model's reasoning is bound to, so they cannot vary between requests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * Laravel validation rules for the input. The schema is enforced by the
     * API (strict mode), but the server never relies on that alone.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $input  Validated against rules().
     */
    public function handle(array $input, ToolContext $context): ToolOutcome;
}
