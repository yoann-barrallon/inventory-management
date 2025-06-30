<?php

declare(strict_types=1);

namespace App\DTOs;

class PurchaseOrderFilterDto
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $supplier = null,
        public readonly ?string $status = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly int $perPage = 15
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            supplier: isset($data['supplier']) ? (int) $data['supplier'] : null,
            status: $data['status'] ?? null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15
        );
    }

    public function hasSearch(): bool
    {
        return !empty($this->search);
    }

    public function hasSupplier(): bool
    {
        return $this->supplier !== null;
    }

    public function hasStatus(): bool
    {
        return !empty($this->status);
    }

    public function hasDateRange(): bool
    {
        return !empty($this->dateFrom) || !empty($this->dateTo);
    }
} 