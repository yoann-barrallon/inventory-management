<?php

declare(strict_types=1);

namespace App\DTOs;

class StockTransferDto
{
    public function __construct(
        public readonly int $productId,
        public readonly int $fromLocationId,
        public readonly int $toLocationId,
        public readonly int $quantity,
        public readonly ?string $reference = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            fromLocationId: (int) $data['from_location_id'],
            toLocationId: (int) $data['to_location_id'],
            quantity: (int) $data['quantity'],
            reference: $data['reference'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'from_location_id' => $this->fromLocationId,
            'to_location_id' => $this->toLocationId,
            'quantity' => $this->quantity,
            'reference' => $this->reference,
        ];
    }

    public function hasReference(): bool
    {
        return !empty($this->reference);
    }
} 