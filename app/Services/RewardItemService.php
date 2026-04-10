<?php

namespace App\Services;

use App\Constants\RewardType;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\RewardItemRepository;
use App\Repositories\UserLogRepository;

class RewardItemService extends BaseService
{
    public function __construct(
        protected RewardItemRepository $rewardItemRepository,
        protected UserLogRepository $userLogRepository
    ) {}

    /**
     * Tạo phần thưởng mới
     */
    public function createRewardItem(array $data): ServiceReturn
    {
        return $this->execute(function () use ($data) {
            $discountAmount = ((int) ($data['reward_type'] ?? 0) === RewardType::Discount->value)
                ? ($data['discount_amount'] ?? null)
                : null;

            $rewardItem = $this->rewardItemRepository->create([
                'name' => $data['name'],
                'points_required' => $data['points_required'],
                'reward_type' => $data['reward_type'],
                'note' => $data['note'] ?? null,
                'discount_amount' => $discountAmount,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->writeRewardItemLog('create_reward_item', 'Tạo phần thưởng ' . $rewardItem->name);

            return ServiceReturn::success($rewardItem, 'Tạo phần thưởng thành công');
        });
    }

    /**
     * Cập nhật phần thưởng
     */
    public function updateRewardItem(int $id, array $data): ServiceReturn
    {
        return $this->execute(function () use ($id, $data) {
            $rewardItem = $this->rewardItemRepository->findById($id);
            if (!$rewardItem) {
                throw new ServiceException('Phần thưởng không tồn tại.');
            }

            $hasRedemptions = $this->rewardItemRepository->hasRedemptions($id);

            // Nếu đã có người đổi, không cho phép sửa points_required
            if ($hasRedemptions && isset($data['points_required']) && $data['points_required'] != $rewardItem->points_required) {
                throw new ServiceException('Không thể thay đổi điểm cần đổi khi đã có học sinh đổi phần thưởng này.');
            }

            if ($hasRedemptions && isset($data['reward_type']) && $data['reward_type'] != $rewardItem->reward_type->value) {
                throw new ServiceException('Không thể thay đổi loại phần thưởng khi đã có học sinh đổi phần thưởng này.');
            }

            if ($hasRedemptions && (($data['discount_amount'] ?? null) != $rewardItem->discount_amount)) {
                throw new ServiceException('Không thể thay đổi giá trị giảm khi đã có học sinh đổi phần thưởng này.');
            }

            $discountAmount = ((int) ($data['reward_type'] ?? $rewardItem->reward_type->value) === RewardType::Discount->value)
                ? ($data['discount_amount'] ?? null)
                : null;

            $updateData = [
                'name' => $data['name'],
                'note' => $data['note'] ?? null,
                'is_active' => $data['is_active'],
                'discount_amount' => $discountAmount,
            ];

            // Chỉ cho phép cập nhật points_required nếu chưa có redemptions
            if (!$hasRedemptions) {
                $updateData['points_required'] = $data['points_required'];
                $updateData['reward_type'] = $data['reward_type'];
            }

            $this->rewardItemRepository->updateById($id, $updateData);
            $this->writeRewardItemLog('update_reward_item', 'Cập nhật phần thưởng ' . $rewardItem->name);

            return ServiceReturn::success($rewardItem->refresh(), 'Cập nhật phần thưởng thành công');
        });
    }

    /**
     * Bật/Tắt trạng thái phần thưởng
     */
    public function toggleStatus(int $id): ServiceReturn
    {
        return $this->execute(function () use ($id) {
            $rewardItem = $this->rewardItemRepository->findById($id);
            if (!$rewardItem) {
                throw new ServiceException('Phần thưởng không tồn tại.');
            }

            $this->rewardItemRepository->updateById($id, [
                'is_active' => !$rewardItem->is_active
            ]);

            $this->writeRewardItemLog(
                'toggle_reward_item',
                ($rewardItem->is_active ? 'Tạm ngưng' : 'Kích hoạt') . ' phần thưởng ' . $rewardItem->name
            );

            return ServiceReturn::success($rewardItem->refresh(), 'Cập nhật trạng thái thành công');
        });
    }

    public function hasRedemptions(string $rewardItemId): bool
    {
        return $this->rewardItemRepository->hasRedemptions($rewardItemId);
    }

    protected function writeRewardItemLog(string $action, string $description): void
    {
        if (! auth()->id()) {
            return;
        }

        $this->userLogRepository->log(auth()->id(), $action, $description);
        Logging::userActivity($action, $description, auth()->id());
    }
}
