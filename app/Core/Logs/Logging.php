<?php

namespace App\Core\Logs;

use Illuminate\Support\Facades\Log;
use Throwable;

class Logging
{
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, self::buildContext($context));
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, self::buildContext($context));
    }

    public static function error(string $message, ?Throwable $exception = null, array $context = []): void
    {
        if ($exception) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        Log::error($message, self::buildContext($context));
    }

    public static function debug(string $message, array $context = []): void
    {
        Log::debug($message, self::buildContext($context));
    }

    public static function buildContext(array $context): array
    {
        return [
            'info' => [
                'ip' => request()?->ip() ?? 'unknown',
                'url' => request()?->fullUrl() ?? 'unknown',
                'user_id' => auth()?->id() ?? 'guest',
            ],
            'context' => $context,
        ];
    }
}
