<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
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
        $location = $this->route('location');
        $locationId = $location instanceof \App\Models\Location ? $location->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations', 'name')->ignore($locationId),
            ],
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
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
            'name' => 'location name',
            'description' => 'location description',
            'address' => 'location address',
            'is_active' => 'active status',
        ];
    }
}
