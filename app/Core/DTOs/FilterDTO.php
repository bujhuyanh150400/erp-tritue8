<?php

namespace App\Core\DTOs;

class FilterDTO
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public ?string $sortBy,
        public string $direction,
        public array $filters
    ) {
    }

    // Bạn có thể thêm setter nếu muốn (hoặc gán trực tiếp $dto->filters = ...)
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
    }

    // Tìm filter theo key
    public function findFilter(string $key)
    {
        return $this->filters[$key] ?? null;
    }

    // Hoặc hàm merge thêm filter mới
    public function addFilter(string $key, mixed $value): void
    {
        $this->filters[$key] = $value;
    }

    // Setter cho sortBy và direction
    public function setSortBy(string $sortBy): void
    {
        $this->sortBy = $sortBy;
    }

    // Setter cho direction
    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }
}
