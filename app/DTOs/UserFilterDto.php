<?php

declare(strict_types=1);

namespace App\DTOs;

class UserFilterDto
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $role = null,
        public readonly ?string $status = null,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly int $perPage = 15
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            role: $data['role'] ?? null,
            status: $data['status'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15
        );
    }

    public function hasSearch(): bool
    {
        return !empty($this->search);
    }

    public function hasRole(): bool
    {
        return !empty($this->role);
    }

    public function hasStatus(): bool
    {
        return !empty($this->status);
    }
} 