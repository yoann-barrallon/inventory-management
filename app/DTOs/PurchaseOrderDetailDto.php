<?php

declare(strict_types=1);

namespace App\DTOs;

class PurchaseOrderDetailDto
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly float $unitPrice,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            quantity: (int) $data['quantity'],
            unitPrice: (float) $data['unit_price'],
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
        ];
    }

    public function getLineTotal(): float
    {
        return $this->quantity * $this->unitPrice;
    }
} 