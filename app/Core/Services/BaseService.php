<?php

namespace App\Core\Services;

use App\Core\Logs\Logging;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    /**
     * Thực thi một hành động với cơ chế transaction (nếu cần).
     *
     * @throws \Throwable
     */
    protected function execute(
        callable $callback,
        bool $useTransaction = false,
        ?string $actionName = null,
        ?callable $catchCallback = null,
        bool $logServiceError = false,
        ?callable $afterCommitCallback = null,
        ?callable $returnCatchCallback = null
    ): ServiceReturn {
        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            $result = $callback();

            if ($useTransaction) {
                if ($afterCommitCallback) {
                    // Đăng ký callback chạy SAU KHI commit thành công
                    DB::afterCommit($afterCommitCallback);
                }
                DB::commit();
            }

            return $result instanceof ServiceReturn ? $result : ServiceReturn::success($result);

        } catch (\Throwable $e) {
            if ($useTransaction) {
                DB::rollBack();
            }

            if ($catchCallback) {
                $catchCallback($e);
            }

            // Tự động xác định Context lỗi
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1]['function'] ?? 'unknown';
            $context = $actionName ?? static::class.'::'.$caller;

            // Phân loại lỗi để Log và Return
            if ($e instanceof ServiceException) {
                if ($logServiceError) {
                    Logging::error("[{$context}] Service Error: ".$e->getMessage(), $e);
                }

                return ServiceReturn::error($e->getMessage(), $e);
            }

            if ($returnCatchCallback) {
                $catchResult = $returnCatchCallback($e);
                // Đảm bảo kết quả luôn là ServiceReturn
                return $catchResult instanceof ServiceReturn
                    ? $catchResult
                    : ServiceReturn::error('Có lỗi xảy ra. Vui lòng thử lại sau.');
            }

            // Lỗi hệ thống nghiêm trọng
            Logging::error("[{$context}] Critical Error: ".$e->getMessage(), $e);

            return ServiceReturn::error('Có lỗi xảy ra. Vui lòng thử lại sau.');
        }
    }
}
