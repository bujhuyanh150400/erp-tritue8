<?php

namespace App\Repositories;

use App\Constants\RewardType;
use App\Core\Repository\BaseRepository;
use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RewardRedemptionRepository extends BaseRepository
{
    public function getModel(): string
    {
        return RewardRedemption::class;
    }

    public function getStudentHistoryQuery(int $studentId): Builder
    {
        return $this->query()
            ->with([
                'rewardItem:id,name,reward_type',
                'processedBy:id,username',
                'processedBy.teacher:id,user_id,full_name',
                'processedBy.staff:id,user_id,full_name',
                'processedBy.student:id,user_id,full_name',
            ])
            ->where('student_id', $studentId)
            ->orderByDesc('redeemed_at');
    }

    public function getUninvoicedMonthlyDiscountRedemptions(int $studentId, Carbon $from, Carbon $to): Collection
    {
        return $this->query()
            ->from('reward_redemptions as rr')
            ->join('reward_items as ri', 'rr.reward_item_id', '=', 'ri.id')
            ->select([
                'rr.id',
                'rr.student_id',
                'rr.reward_item_id',
                'rr.redeemed_at',
                'ri.discount_amount',
            ])
            ->where('rr.student_id', $studentId)
            ->where('ri.reward_type', RewardType::Discount->value)
            ->whereNull('rr.invoice_id')
            ->whereBetween('rr.redeemed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('rr.redeemed_at')
            ->get();
    }

    public function attachInvoiceToRedemptions(array $redemptionIds, int $invoiceId): int
    {
        if (empty($redemptionIds)) {
            return 0;
        }

        return $this->query()
            ->whereIn('id', $redemptionIds)
            ->update([
                'invoice_id' => $invoiceId,
            ]);
    }
}
