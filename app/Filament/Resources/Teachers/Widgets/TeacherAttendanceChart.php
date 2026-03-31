<?php

namespace App\Filament\Resources\Teachers\Widgets;

use App\Models\Teacher;
use App\Services\TeacherService;
use Filament\Widgets\ChartWidget;

class TeacherAttendanceChart extends ChartWidget
{
    protected ?string $heading = 'Tỷ lệ chuyên cần các lớp';
    public ?Teacher $record = null;
    public string $selectedMonth;

    protected function getData(): array
    {
        if (!$this->record) {
            return [];
        }

        $service = app(TeacherService::class);
        $result = $service->getAttendanceChartData($this->record->id, $this->selectedMonth);
        $data = $result->getData() ?? [];

        $labels = array_column($data, 'name');
        $dataValues = array_column($data, 'rate');

        return [
            'datasets' => [
                [
                    'label' => 'Tỷ lệ chuyên cần (%)',
                    'data' => $dataValues,
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#9BD0F5',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
