<?php

declare(strict_types=1);

namespace App\DTOs;

class ProductFilterDto
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $category = null,
        public readonly ?int $supplier = null,
        public readonly ?string $status = null,
        public readonly bool $lowStock = false,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly int $perPage = 15
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            category: isset($data['category']) ? (int) $data['category'] : null,
            supplier: isset($data['supplier']) ? (int) $data['supplier'] : null,
            status: $data['status'] ?? null,
            lowStock: ($data['low_stock'] ?? false) === 'true',
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15
        );
    }

    public function hasSearch(): bool
    {
        return !empty($this->search);
    }

    public function hasCategory(): bool
    {
        return $this->category !== null;
    }

    public function hasSupplier(): bool
    {
        return $this->supplier !== null;
    }

    public function hasStatus(): bool
    {
        return !empty($this->status);
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'category' => $this->category,
            'supplier' => $this->supplier,
            'status' => $this->status,
            'low_stock' => $this->lowStock ? 'true' : 'false',
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'per_page' => $this->perPage,
        ];
    }
} 