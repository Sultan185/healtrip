<?php

namespace App\Providers;

use App\Agent\Guardrails\OutputGuard;
use App\Agent\Llm\LlmManager;
use App\Agent\Tools\PresentRecommendationTool;
use App\Agent\Tools\SearchDoctorsTool;
use App\Agent\Tools\SearchHospitalsTool;
use App\Agent\Tools\ToolRegistry;
use App\Directory\ProviderSearch;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmManager::class);

        // The agent's whole capability surface is this list. Every tool is
        // read-only against the directory or writes only a recommendation.
        $this->app->singleton(ToolRegistry::class, fn (Application $app) => new ToolRegistry([
            new SearchDoctorsTool($app->make(ProviderSearch::class)),
            new SearchHospitalsTool($app->make(ProviderSearch::class)),
            new PresentRecommendationTool($app->make(OutputGuard::class)),
        ]));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        DB::prohibitDestructiveCommands($this->app->isProduction());
        Model::shouldBeStrict(! $this->app->isProduction());
        JsonResource::withoutWrapping();

        // Each message costs a model call, so sending is limited per minute
        // (bursts) and per day (total spend) for every client address.
        RateLimiter::for('chat', fn (Request $request) => [
            Limit::perMinute((int) config('healtrip.rate_limits.messages_per_minute'))->by('chat:minute:'.$request->ip()),
            Limit::perDay((int) config('healtrip.rate_limits.messages_per_day'))->by('chat:day:'.$request->ip()),
        ]);

        RateLimiter::for('conversations', fn (Request $request) => Limit::perMinute(20)->by('conversations:'.$request->ip()));
    }
}
