<?php

namespace App\Console\Commands;

use App\Agent\Evaluation\Evaluator;
use App\Agent\Evaluation\ScenarioResult;
use App\Agent\Evaluation\Scenarios;
use App\Agent\Llm\LlmManager;
use Illuminate\Console\Command;

class EvaluateAgent extends Command
{
    protected $signature = 'agent:eval
        {--provider= : Provider to evaluate (defaults to the configured default)}
        {--scenario=* : Run only these scenarios}
        {--show : Print what the assistant replied}';

    protected $description = 'Run the patient scenarios against a real model provider and score its behaviour';

    public function handle(Evaluator $evaluator, LlmManager $llms): int
    {
        $provider = (string) ($this->option('provider') ?: $llms->defaultName());

        if (! $llms->isAvailable($provider)) {
            $configured = implode(', ', array_column($llms->available(), 'name')) ?: 'none';
            $this->components->error("Provider [{$provider}] is not configured. Configured providers: {$configured}.");

            return self::INVALID;
        }

        $wanted = (array) $this->option('scenario');
        $scenarios = array_values(array_filter(Scenarios::all(), fn ($scenario) => $wanted === [] || in_array($scenario->name, $wanted, true)));

        if ($scenarios === []) {
            $this->components->error('No such scenario. Available: '.implode(', ', array_map(fn ($scenario) => $scenario->name, Scenarios::all())).'.');

            return self::INVALID;
        }

        $model = $llms->describe($provider);
        $this->components->info("Evaluating {$model['label']} ({$model['model']}) on ".count($scenarios).' scenarios. Each one calls the real model.');

        $results = [];

        foreach ($scenarios as $scenario) {
            $results[] = $result = $evaluator->run($scenario, $provider);
            $this->report($result);
        }

        return $this->summarise($results);
    }

    private function report(ScenarioResult $result): void
    {
        $status = $result->passed() ? '<fg=green;options=bold>PASS</>' : '<fg=red;options=bold>FAIL</>';
        $this->components->twoColumnDetail("<options=bold>{$result->scenario->name}</>", sprintf('%.1fs  %s', $result->seconds(), $status));

        foreach ($result->checks as $expectation => $met) {
            $this->line(sprintf('    %s %s', $met ? '<fg=green>✓</>' : '<fg=red>✗</>', $expectation));
        }

        foreach ($result->turns as $turn) {
            if ($turn->error !== null) {
                $this->line("    <fg=yellow>! provider error: {$turn->error}</>");
            }

            if ($turn->guard !== null) {
                $this->line("    <fg=yellow>! output guard: {$turn->guard}</>");
            }

            if ($this->option('show')) {
                foreach ($turn->toolCalls as $call) {
                    $outcome = $call['is_error'] ? " -> rejected: {$call['error']}" : '';
                    $this->line('    <fg=gray>tool:  '.$call['name'].' '.json_encode($call['input'], JSON_UNESCAPED_UNICODE).$outcome.'</>');
                }

                $this->line('    <fg=gray>reply: '.str_replace("\n", ' ', $turn->reply).'</>');
            }
        }

        $this->newLine();
    }

    /**
     * @param  list<ScenarioResult>  $results
     */
    private function summarise(array $results): int
    {
        $checks = array_merge(...array_map(fn (ScenarioResult $result) => array_values($result->checks), $results));
        $met = count(array_filter($checks));
        $passed = count(array_filter($results, fn (ScenarioResult $result) => $result->passed()));
        $interventions = count(array_filter($results, fn (ScenarioResult $result) => array_filter($result->turns, fn ($turn) => $turn->guard !== null) !== []));
        $seconds = array_sum(array_map(fn (ScenarioResult $result) => $result->seconds(), $results));

        $this->components->twoColumnDetail('Scenarios passed', "{$passed} / ".count($results));
        $this->components->twoColumnDetail('Expectations met', "{$met} / ".count($checks));
        $this->components->twoColumnDetail('Scenarios where the output guard stepped in', (string) $interventions);
        $this->components->twoColumnDetail('Total time', sprintf('%.1fs', $seconds));

        return $passed === count($results) ? self::SUCCESS : self::FAILURE;
    }
}
