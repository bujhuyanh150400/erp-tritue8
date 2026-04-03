<?php

namespace App\Services;

use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\RewardItemRepository;
use App\Repositories\RewardPointRepository;
use App\Repositories\RewardRedemptionRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserLogRepository;

class RewardRedemptionService extends BaseService
{
    public function __construct(
        protected RewardItemRepository $rewardItemRepository,
        protected RewardPointRepository $rewardPointRepository,
        protected RewardRedemptionRepository $rewardRedemptionRepository,
        protected StudentRepository $studentRepository,
        protected UserLogRepository $userLogRepository,
    ) {}

    public function getCatalogForRedemption(int $studentId): ServiceReturn
    {
        return $this->execute(function () use ($studentId) {
            $student = $this->studentRepository->findStudentById($studentId);

            if (! $student) {
                throw new ServiceException('Học sinh không tồn tại.');
            }

            $currentPoints = $this->rewardPointRepository->getStudentBalance($studentId);
            $items = $this->rewardItemRepository->getActiveCatalog()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'points_required' => $item->points_required,
                    'reward_type' => $item->reward_type,
                    'reward_type_label' => $item->reward_type->label(),
                    'can_redeem' => $currentPoints >= $item->points_required,
                    'label' => $this->formatRewardItemLabel($item),
                ])
                ->values()
                ->all();

            return [
                'student' => $student,
                'current_points' => $currentPoints,
                'items' => $items,
            ];
        });
    }

    public function redeemForStudent(int $studentId, int $rewardItemId): ServiceReturn
    {
        return $this->execute(function () use ($studentId, $rewardItemId) {
            $student = $this->studentRepository->findStudentById($studentId);

            if (! $student) {
                throw new ServiceException('Học sinh không tồn tại.');
            }

            $rewardItem = $this->rewardItemRepository->findActiveById($rewardItemId);

            if (! $rewardItem) {
                throw new ServiceException('Phần thưởng không tồn tại hoặc đang tạm ngưng.');
            }

            $currentPoints = $this->rewardPointRepository->getStudentBalance($studentId);

            if ($currentPoints < $rewardItem->points_required) {
                throw new ServiceException(
                    "Học sinh chỉ có {$currentPoints} sao, cần {$rewardItem->points_required} sao để đổi {$rewardItem->name}."
                );
            }

            $actorId = auth()->id();
            $actorName = auth()->user()?->name ?? 'Hệ thống';

            if (! $actorId) {
                throw new ServiceException('Không xác định được người xử lý.');
            }

            $redemption = $this->rewardRedemptionRepository->create([
                'student_id' => $student->id,
                'reward_item_id' => $rewardItem->id,
                'points_spent' => $rewardItem->points_required,
                'redeemed_at' => now(),
                'processed_by' => $actorId,
            ]);

            $this->rewardPointRepository->create([
                'student_id' => $student->id,
                'session_id' => null,
                'amount' => -$rewardItem->points_required,
                'reason' => 'Đổi thưởng: ' . $rewardItem->name,
                'awarded_by' => $actorId,
            ]);

            $description = "{$actorName} đã đổi thưởng {$rewardItem->name} cho học sinh {$student->full_name} vào lúc {$redemption->redeemed_at->format('d/m/Y H:i:s')}.";

            $this->userLogRepository->log(
                userId: $actorId,
                action: 'redeem_reward',
                description: $description,
            );

            Logging::userActivity(
                action: 'Đổi thưởng',
                description: $description,
                userId: $actorId,
            );

            return [
                'redemption' => $redemption->load(['rewardItem', 'processedBy']),
                'remaining_points' => $currentPoints - $rewardItem->points_required,
            ];
        }, useTransaction: true);
    }

    protected function formatRewardItemLabel(object $item): string
    {
        $segments = [
            $item->name,
            $item->points_required . ' sao',
            $item->reward_type->label(),
        ];

        return implode(' | ', $segments);
    }
}
