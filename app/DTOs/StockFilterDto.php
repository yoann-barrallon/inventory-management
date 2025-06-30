<?php

declare(strict_types=1);

namespace App\DTOs;

class StockFilterDto
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $product = null,
        public readonly ?int $location = null,
        public readonly ?string $stockLevel = null, // 'low', 'zero', 'overstock'
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly int $perPage = 15
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            product: isset($data['product']) ? (int) $data['product'] : null,
            location: isset($data['location']) ? (int) $data['location'] : null,
            stockLevel: $data['stock_level'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15
        );
    }

    public function hasSearch(): bool
    {
        return !empty($this->search);
    }

    public function hasProduct(): bool
    {
        return $this->product !== null;
    }

    public function hasLocation(): bool
    {
        return $this->location !== null;
    }

    public function hasStockLevel(): bool
    {
        return !empty($this->stockLevel);
    }
} 