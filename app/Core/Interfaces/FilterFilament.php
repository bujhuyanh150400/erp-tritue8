<?php

namespace App\Core\Interfaces;

use Illuminate\Database\Eloquent\Builder;

/**
 * interface dành cho filter List của filament
 */
interface FilterFilament
{

    /**
 *  Lấy query danh sách record theo pagination.
     * @param Builder $query
     * @return Builder
     */
    public function getListingQuery(Builder $query): Builder;
    /**
     *  Hàm lọc các điều kiện
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function setFilters(Builder $query, array $filters = []): Builder;
}
