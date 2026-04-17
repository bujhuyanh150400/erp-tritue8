<?php

namespace App\Filament\Resources\Classes\Components;

use App\Models\ClassEnrollment;
use App\Repositories\ClassEnrollmentRepository;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class FeeHistoryTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $classId;
    public int $studentId;
    public $baseFee; // Hứng học phí gốc để hiển thị nếu record bị NULL

    public function mount(int $classId, int $studentId, $baseFee): void
    {
        $this->classId = $classId;
        $this->studentId = $studentId;
        $this->baseFee = $baseFee;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (ClassEnrollmentRepository $repository) {
                return $repository->query()
                    ->where('class_id', $this->classId)
                    ->where('student_id', $this->studentId)
                    ->orderBy('fee_effective_from', 'asc');
            })
            ->columns([
                TextColumn::make('fee_per_session')
                    ->label('Học phí / buổi')
                    ->money('VND')
                    ->default($this->baseFee) // Fallback về giá gốc
                    ->description(fn ($record) => is_null($record->fee_per_session) ? '(Mức cơ bản của lớp)' : null),

                TextColumn::make('fee_effective_from')
                    ->label('Từ ngày')
                    ->date('d/m/Y'),

                TextColumn::make('fee_effective_to')
                    ->label('Đến ngày')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y') : 'Hiện hành')
                    ->badge()
                    // 2. Xét màu dựa trên giá trị gốc của DB (DB trả về null nghĩa là đang hiện hành)
                    ->color(fn ($state) => is_null($state) ? 'success' : 'gray'),
            ])
            ->emptyStateHeading('Chưa có lịch sử thay đổi')
            ->emptyStateDescription('Học sinh này hiện đang áp dụng mức học phí mặc định từ khi vào lớp.')
            ->emptyStateIcon(Heroicon::OutlinedClock)
            ->heading('Lịch sử cấu hình học phí');
    }

    public function render(): View
    {
        return view('filament.common.view-table');
    }
}
