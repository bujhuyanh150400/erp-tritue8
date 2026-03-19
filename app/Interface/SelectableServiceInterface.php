<?php

namespace App\Interface;

use App\Core\Services\ServiceReturn;

interface SelectableServiceInterface
{
    /**
     * Lấy danh sách các tùy chọn có thể chọn
     * @param string|null $search
     * @return ServiceReturn
     */
    public function getOptions(?string $search = null): ServiceReturn;

    /**
     * Lấy nhãn của tùy chọn có thể chọn theo id
     * @param mixed $id
     * @return ServiceReturn
     */
    public function getLabelOption(mixed $id): ServiceReturn;
}
