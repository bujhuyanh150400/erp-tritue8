<?php

namespace App\Filament\Components;

use App\Interface\SelectableServiceInterface;
use Filament\Forms\Components\Select;
use Illuminate\Contracts\Container\BindingResolutionException;

class CustomSelect extends Select
{
    // Biến lưu trữ các bộ lọc tùy chỉnh
    protected \Closure|array $serviceFilters = [];

    /**
     * Hàm để form truyền các điều kiện lọc bổ sung
     * @param \Closure|array $filters - Mảng hoặc Closure trả về mảng các bộ lọc bổ sung
     */
    public function serviceFilters(\Closure|array $filters): static
    {
        $this->serviceFilters = $filters;
        return $this;
    }

    /**
     * Nạp Service vào Select để lấy danh sách các tùy chọn thông qua query tùy chọn
     * @param class-string<SelectableServiceInterface> $serviceClass - Service phải implement SelectableServiceInterface
     * @return CustomSelect
     * @throws BindingResolutionException
     */
    public function getOptionSelectService(string $serviceClass): static
    {
        $service = app()->make($serviceClass);
        return $this
            ->searchable()
            ->options(function () use ($service) {
                // Dùng evaluate() để thực thi Closure, lấy ra mảng filters ở thời điểm runtime
                $filters = $this->evaluate($this->serviceFilters) ?? [];
                // Gọi getOptions() với các bộ lọc bổ sung
                $result = $service->getOptions(search: null, filters: $filters);
                return $result->isSuccess() ? $result->getData() : [];
            })
            ->getSearchResultsUsing(function (string $search) use ($service) {
                $filters = $this->evaluate($this->serviceFilters) ?? [];
                $result = $service->getOptions($search, $filters);
                return $result->isSuccess() ? $result->getData() : [];
            })
            ->getOptionLabelUsing(function ($value) use ($service) {
                if (empty($value)) return null;
                $result = $service->getLabelOption($value);
                return $result->isSuccess() ? $result->getData() : null;
            });
    }
}
