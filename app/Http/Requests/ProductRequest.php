<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof \App\Models\Product ? $product->id : null;

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'barcode')->ignore($productId),
            ],
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit_price' => 'required|numeric|min:0|max:999999.99',
            'cost_price' => 'required|numeric|min:0|max:999999.99',
            'min_stock_level' => 'required|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'product name',
            'description' => 'product description',
            'sku' => 'SKU',
            'barcode' => 'barcode',
            'category_id' => 'category',
            'supplier_id' => 'supplier',
            'unit_price' => 'unit price',
            'cost_price' => 'cost price',
            'min_stock_level' => 'minimum stock level',
            'is_active' => 'active status',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category does not exist.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'unit_price.max' => 'The unit price may not be greater than 999,999.99.',
            'cost_price.max' => 'The cost price may not be greater than 999,999.99.',
        ];
    }
}
