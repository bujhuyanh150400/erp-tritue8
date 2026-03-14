<?php

namespace App\Core\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

interface Paginate
{
    /**
     * Lấy danh sách record theo pagination.
     * @param int $perPage Số lượng record trên 1 trang
     * @param int $page Trang hiện tại
     * @param array $filters Các điều kiện lọc
     * @param string $orderBy Cột cần sắp xếp
     * @param string $orderDirection Chiều sắp xếp (asc/desc)
     */
    public function paginate(
        array  $filters = [],
        int    $perPage = 10,
        int    $page = 1,
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): LengthAwarePaginator;

    /**
     *  Hàm lọc các điều kiện
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function filters(Builder $query, array $filters = []): Builder;

    /**
     * Sắp xếp query theo cột và chiều.
     * @param Builder $query
     * @param string $orderBy Cột cần sắp xếp
     * @param string $orderDirection Chiều sắp xếp (asc/desc)
     * @return Builder
     */
    public function sort(Builder $query, string $orderBy, string $orderDirection): Builder;
}
