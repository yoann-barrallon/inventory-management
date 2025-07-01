<?php

declare(strict_types=1);

namespace App\DTOs;

class CreatePurchaseOrderDto
{
    /**
     * @param PurchaseOrderDetailDto[] $details
     */
    public function __construct(
        public readonly int $supplierId,
        public readonly ?string $orderDate = null,
        public readonly ?string $expectedDate = null,
        public readonly ?string $notes = null,
        public readonly ?float $taxRate = null,
        public readonly array $details = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $details = [];
        if (isset($data['details']) && is_array($data['details'])) {
            $details = array_map(
                fn(array $detail) => PurchaseOrderDetailDto::fromArray($detail),
                $data['details']
            );
        }

        return new self(
            supplierId: (int) $data['supplier_id'],
            orderDate: $data['order_date'] ?? null,
            expectedDate: $data['expected_date'] ?? null,
            notes: $data['notes'] ?? null,
            taxRate: isset($data['tax_rate']) ? (float) $data['tax_rate'] : null,
            details: $details,
        );
    }

    public function toArray(): array
    {
        return [
            'supplier_id' => $this->supplierId,
            'order_date' => $this->orderDate,
            'expected_date' => $this->expectedDate,
            'notes' => $this->notes,
            'tax_rate' => $this->taxRate,
            'details' => array_map(fn(PurchaseOrderDetailDto $detail) => $detail->toArray(), $this->details),
        ];
    }

    public function hasOrderDate(): bool
    {
        return !empty($this->orderDate);
    }

    public function hasExpectedDate(): bool
    {
        return !empty($this->expectedDate);
    }

    public function hasNotes(): bool
    {
        return !empty($this->notes);
    }

    public function hasTaxRate(): bool
    {
        return $this->taxRate !== null;
    }

    public function hasDetails(): bool
    {
        return !empty($this->details);
    }

    public function getDetailsCount(): int
    {
        return count($this->details);
    }

    public function getTotalQuantity(): int
    {
        return array_sum(array_map(fn(PurchaseOrderDetailDto $detail) => $detail->quantity, $this->details));
    }

    public function getSubtotal(): float
    {
        return array_sum(array_map(fn(PurchaseOrderDetailDto $detail) => $detail->getLineTotal(), $this->details));
    }
} 