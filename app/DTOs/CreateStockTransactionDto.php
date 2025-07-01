<?php

declare(strict_types=1);

namespace App\DTOs;

class CreateStockTransactionDto
{
    public function __construct(
        public readonly int $productId,
        public readonly int $locationId,
        public readonly string $type,
        public readonly int $quantity,
        public readonly ?string $reference = null,
        public readonly ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            locationId: (int) $data['location_id'],
            type: $data['type'],
            quantity: (int) $data['quantity'],
            reference: $data['reference'] ?? null,
            reason: $data['reason'] ?? $data['notes'] ?? null, // Support both for backward compatibility
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'location_id' => $this->locationId,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'reference' => $this->reference,
            'reason' => $this->reason,
        ];
    }

    public function hasReference(): bool
    {
        return !empty($this->reference);
    }

    public function hasReason(): bool
    {
        return !empty($this->reason);
    }
} 