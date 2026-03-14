<?php

namespace App\Core\DTOs;

readonly class FilterDTO
{
    public function __construct(
        private int $page,
        private int $perPage,
        private ?string $sortBy,
        private string $direction,
        private array $filters
    ) {}

    /**
     * Lấy page hiện tại.
     */
    final public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Lấy số lượng item trên mỗi page.
     */
    final public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Lấy field để sắp xếp.
     */
    final public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    /**
     * Lấy hướng sắp xếp (asc hoặc desc).
     */
    final public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * Lấy các điều kiện lọc.
     */
    final public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Chuyển đổi đối tượng FilterDTO thành mảng.
     */
    final public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort_by' => $this->sortBy,
            'direction' => $this->direction,
        ];
    }
}
