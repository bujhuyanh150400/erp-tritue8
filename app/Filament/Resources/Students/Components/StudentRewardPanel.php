<?php

namespace App\Filament\Resources\Students\Components;

use App\Models\Student;
use App\Repositories\RewardPointRepository;
use App\Repositories\RewardRedemptionRepository;
use App\Services\RewardRedemptionService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Filament\Support\Icons\Heroicon;


class StudentRewardPanel extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Student $record;
    public int $totalPoints = 0;

    public function mount(): void
    {
        $this->totalPoints = app(RewardPointRepository::class)->getStudentBalance($this->record->id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (RewardRedemptionRepository $repository) {
                return $repository->getStudentHistoryQuery($this->record->id);
            })
            ->columns([
                TextColumn::make('redeemed_at')
                    ->label('Thời gian đổi')
                    ->dateTime('d/m/Y H:i'),

                TextColumn::make('rewardItem.name')
                    ->label('Tên phần thưởng')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('rewardItem.reward_type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-'),

                TextColumn::make('points_spent')
                    ->label('Điểm đã đổi')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => $state . ' sao'),

                TextColumn::make('processedBy.username')
                    ->label('Người xử lý')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->processedBy?->teacher?->full_name
                            ?? $record->processedBy?->staff?->full_name
                            ?? $record->processedBy?->student?->full_name
                            ?? $record->processedBy?->username
                            ?? '-';
                    }),
            ])
            ->headerActions([
                Action::make('redeemReward')
                    ->label('Đổi thưởng')
                    ->icon('heroicon-m-gift')
                    ->color('warning')
                    ->modalHeading('Đổi thưởng cho học sinh')
                    ->icon(Heroicon::Star)
                    ->modalDescription('Tổng sao hiện có: ' . $this->totalPoints)
                    ->schema([
                        Select::make('reward_item_id')
                            ->label('Phần thưởng')
                            ->required()
                            ->searchable()
                            ->options(function () {
                                $service = app(RewardRedemptionService::class);
                                $result = $service->getCatalogForRedemption($this->record->id);

                                if ($result->isError()) {
                                    return [];
                                }

                                return collect($result->getData()['items'] ?? [])
                                    ->mapWithKeys(fn (array $item) => [$item['id'] => $item['label']])
                                    ->all();
                            }),
                    ])
                    ->action(function (array $data) {
                        $service = app(RewardRedemptionService::class);
                        $result = $service->redeemForStudent($this->record->id, (int) $data['reward_item_id']);

                        if ($result->isError()) {
                            Notification::make()
                                ->title('Lỗi đổi thưởng')
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                            throw new Halt();
                        }

                        Notification::make()
                            ->title('Đổi thưởng thành công')
                            ->success()
                            ->send();

                        $this->totalPoints = (int) ($result->getData()['remaining_points'] ?? $this->totalPoints);
                        $this->resetTable();
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('redeemed_at', 'desc')
            ->emptyStateHeading('Chưa có lịch sử đổi thưởng');
    }

    public function render(): View
    {
        return view('filament.pages.student.student-reward-panel', [
            'totalPoints' => $this->totalPoints,
        ]);
    }
}
