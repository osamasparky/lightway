<?php

namespace App\Services\Localization\AI;

use RuntimeException;

class AITranslationException extends RuntimeException
{
    public const AUTH = 'auth';                 // invalid / revoked key -> stop the job
    public const RATE_LIMIT = 'rate_limit';     // wait and retry
    public const TIMEOUT = 'timeout';           // retry
    public const SERVER = 'server';             // provider 5xx -> retry
    public const NETWORK = 'network';           // connection problem -> retry
    public const INVALID_RESPONSE = 'invalid';  // malformed / incomplete JSON -> retry
    public const REQUEST = 'request';           // 4xx other than auth/rate (bad model, too many tokens) -> stop
    public const NOT_CONFIGURED = 'not_configured';

    public function __construct(
        public readonly string $type,
        string $message,
        public readonly ?int $retryAfter = null
    ) {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return in_array($this->type, [self::RATE_LIMIT, self::TIMEOUT, self::SERVER, self::NETWORK, self::INVALID_RESPONSE]);
    }
}
