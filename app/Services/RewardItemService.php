<?php

namespace App\Services;

use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\RewardItem;
use App\Repositories\RewardItemRepository;

class RewardItemService extends BaseService
{
    public function __construct(
        protected RewardItemRepository $rewardItemRepository
    ) {}

    /**
     * Tạo phần thưởng mới
     */
    public function createRewardItem(array $data): ServiceReturn
    {
        return $this->execute(function () use ($data) {
            $rewardItem = $this->rewardItemRepository->create([
                'name' => $data['name'],
                'points_required' => $data['points_required'],
                'reward_type' => $data['reward_type'],
                'note' => $data['note'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

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

            $updateData = [
                'name' => $data['name'],
                'reward_type' => $data['reward_type'],
                'note' => $data['note'] ?? null,
                'is_active' => $data['is_active'],
            ];

            // Chỉ cho phép cập nhật points_required nếu chưa có redemptions
            if (!$hasRedemptions) {
                $updateData['points_required'] = $data['points_required'];
            }

            $this->rewardItemRepository->updateById($id, $updateData);

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

            return ServiceReturn::success($rewardItem->refresh(), 'Cập nhật trạng thái thành công');
        });
    }

    public function hasRedemptions(string $rewardItemId): bool
    {
        return $this->rewardItemRepository->hasRedemptions($rewardItemId);
    }
}
