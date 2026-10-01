<?php

namespace App\Agent\Tools;

use Illuminate\Support\Facades\Validator;
use Throwable;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  iterable<Tool>  $tools
     */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }

        ksort($this->tools);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_values(array_map(fn (Tool $tool) => $tool->definition(), $this->tools));
    }

    /**
     * Run a tool the model asked for. This never throws: an unknown tool, bad
     * arguments or a crash inside the tool all come back as an error result
     * the model can act on.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(string $name, array $input, ToolContext $context): ToolOutcome
    {
        $tool = $this->tools[$name] ?? null;

        if ($tool === null) {
            return ToolOutcome::error('unknown_tool', "There is no tool named \"{$name}\".", [
                'available_tools' => array_keys($this->tools),
            ]);
        }

        $validator = Validator::make($input, $tool->rules());

        if ($validator->fails()) {
            return ToolOutcome::error('invalid_input', 'The tool input is invalid. Fix it and call the tool again.', [
                'problems' => $validator->errors()->all(),
            ]);
        }

        try {
            /** @var array<string, mixed> $validated */
            $validated = $validator->validated();

            return $tool->handle($validated, $context);
        } catch (Throwable $e) {
            report($e);

            return ToolOutcome::error('tool_failed', 'The tool failed unexpectedly. Do not guess its result; tell the patient the directory is temporarily unavailable.');
        }
    }
}
