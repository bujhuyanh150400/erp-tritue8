<?php
namespace App\Core\Traits;

use App\Core\Helpers\Snowflake;

trait HasBigIntId
{
    protected static function bootHasBigIntId(): void
    {
        static::creating(function ($model) {
            // Chỉ gán nếu ID chưa được set
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Snowflake::id();
            }
        });
    }

    /**
     * Báo cho Eloquent biết khóa chính không tự tăng (non-incrementing).
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Báo cho Eloquent biết kiểu dữ liệu của khóa chính là 'int' (BIGINT).
     */
    public function getKeyType(): string
    {
        return 'int';
    }
}
