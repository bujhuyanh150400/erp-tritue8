<?php

namespace App\Filament\Resources\Teachers\Components;

use App\Filament\Resources\Teachers\Widgets\TeacherAttendanceChart;
use App\Filament\Resources\Teachers\Widgets\TeacherKpiStatsOverview;
use App\Models\Teacher;
use App\Services\TeacherService;
use Livewire\Component;

class TeacherKpiOverview extends Component
{
    public Teacher $record;

    public string $selectedMonth;



    public function mount(Teacher $record)
    {
        $this->record = $record;
        $this->selectedMonth = now()->format('Y-m');
    }

    public function render()
    {
        $service = app(TeacherService::class);
        $result = $service->getKpiOverview($this->record->id, $this->selectedMonth);

        $data = $result->getData();

        return view('filament.resources.teachers.components.teacher-kpi-overview', [
            'stats' => $data['stats'] ?? [],
            'warnings' => $data['warnings'] ?? [],
        ]);
    }
}
