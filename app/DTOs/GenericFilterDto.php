<?php

declare(strict_types=1);

namespace App\DTOs;

class GenericFilterDto
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly int $perPage = 10
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 10
        );
    }

    public function hasSearch(): bool
    {
        return !empty($this->search);
    }

    public function hasStatus(): bool
    {
        return !empty($this->status);
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'per_page' => $this->perPage,
        ];
    }
} 