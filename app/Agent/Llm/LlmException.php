<?php

namespace App\Agent\Llm;

use RuntimeException;
use Throwable;

/**
 * A provider failure reduced to what the rest of the app needs to know:
 * a stable code for the UI and whether trying again can help.
 */
class LlmException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const AUTH = 'auth';

    public const RATE_LIMITED = 'rate_limited';

    public const UNAVAILABLE = 'unavailable';

    public const BAD_REQUEST = 'bad_request';

    final public function __construct(
        public readonly string $reason,
        public readonly bool $retryable,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(string $provider): static
    {
        return new static(self::NOT_CONFIGURED, false, "The model provider [{$provider}] is not configured.");
    }

    public static function auth(Throwable $previous): static
    {
        return new static(self::AUTH, false, 'The model provider rejected the API credentials.', $previous);
    }

    public static function rateLimited(Throwable $previous): static
    {
        return new static(self::RATE_LIMITED, true, 'The model provider is rate limiting requests.', $previous);
    }

    public static function unavailable(Throwable $previous): static
    {
        return new static(self::UNAVAILABLE, true, 'The model provider could not be reached or failed.', $previous);
    }

    public static function badRequest(Throwable $previous): static
    {
        return new static(self::BAD_REQUEST, false, 'The model provider rejected the request.', $previous);
    }
}
