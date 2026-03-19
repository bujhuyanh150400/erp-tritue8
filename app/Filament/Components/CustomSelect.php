<?php

namespace App\Filament\Components;

use App\Interface\SelectableServiceInterface;
use Filament\Forms\Components\Select;

class CustomSelect extends Select
{
    /**
     * Nạp Service vào Select để lấy danh sách các tùy chọn thông qua query tùy chọn
     * @param class-string<SelectableServiceInterface> $serviceClass - Service phải implement SelectableServiceInterface
     * @param string $label
     * @return CustomSelect
     */
    public function getOptionSelectService(string $serviceClass): static
    {
        $service = app()->make($serviceClass);
        return $this
            ->searchable()
            ->options(function () use ($service) {
                $result = $service->getOptions();
                return $result->isSuccess() ? $result->getData() : [];
            })
            ->getSearchResultsUsing(function (string $search) use ($service) {
                $result = $service->getOptions($search);
                return $result->isSuccess() ? $result->getData() : [];
            })
            ->getOptionLabelUsing(function ($value) use ($service) {
                if (empty($value)) return null;
                $result = $service->getLabelOption($value);
                return $result->isSuccess() ? $result->getData() : null;
            });
    }
}
