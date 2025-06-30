<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string|max:1000',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'details' => 'nullable|array|min:1',
            'details.*.product_id' => 'required_with:details|exists:products,id',
            'details.*.quantity' => 'required_with:details|integer|min:1',
            'details.*.unit_price' => 'required_with:details|numeric|min:0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'order_date' => 'order date',
            'expected_date' => 'expected delivery date',
            'tax_rate' => 'tax rate',
            'details.*.product_id' => 'product',
            'details.*.quantity' => 'quantity',
            'details.*.unit_price' => 'unit price',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'details.min' => 'At least one product must be added to the purchase order.',
            'details.*.product_id.required_with' => 'Product is required for each order line.',
            'details.*.quantity.required_with' => 'Quantity is required for each order line.',
            'details.*.unit_price.required_with' => 'Unit price is required for each order line.',
            'expected_date.after_or_equal' => 'Expected delivery date must be on or after the order date.',
        ];
    }
}
