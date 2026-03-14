<?php

namespace App\Core\Logs;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class Logging
 * Lớp tiện ích (helper) để ghi log ra các kênh file riêng biệt
 * đã được định nghĩa trong config/logging.php.
 */
class Logging
{
    /**
     * Ghi log HÀNH ĐỘNG (Nghiệp vụ) vào kênh 'user_activity'.
     * (Sẽ được lưu vào storage/logs/user_activity-YYYY-MM-DD.log)
     */
    public static function userActivity(
        string $action,
        string $description,
        ?int $userId = null
    ): void {
        $userRequestId = request()->user()?->id;
        if ($userId) {
            $userRequestId = $userId;
        }
        if (! empty($userRequestId)) {
            $userRequestId = 'guest';
        }
        $context = [
            'user_id' => $userRequestId,
            'action' => $action,
            'description' => $description,
        ];
        $message = "User {$userRequestId} - Action: {$action} - {$description}";
        Log::channel('user_activity')->info($message, self::buildContext($context));
    }

    /**
     * Ghi log LỖI (Error) vào kênh 'errors'.
     * (Sẽ được lưu vào storage/logs/errors-YYYY-MM-DD.log)
     */
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

        Log::channel('errors')->error($message, self::buildContext($context));
    }

    /**
     * Ghi log DEBUG vào kênh 'debug'.
     * (Sẽ được lưu vào storage/logs/debug-YYYY-MM-DD.log)
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::channel('debug')->debug($message, self::buildContext($context));
    }

    /**
     * Xây dựng context log chung bao gồm thông tin request và context bổ sung.
     */
    public static function buildContext(array $context): array
    {
        return [
            'request' => [
                'ip' => request()?->ip() ?? 'unknown',
                'url' => request()?->fullUrl() ?? 'unknown',
            ],
            'context' => $context,
        ];
    }
}
