<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockTransactionRequest extends FormRequest
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
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:locations,id',
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'location_id' => 'location',
            'type' => 'transaction type',
            'quantity' => 'quantity',
            'reference' => 'reference number',
            'reason' => 'reason',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Transaction type must be stock in, stock out, or adjustment.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
