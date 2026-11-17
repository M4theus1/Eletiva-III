<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'code' => 'nullable|string|max:100|unique:products,code',
            'price' => 'required|numeric|min:0|max:99999999.99|decimal:0,2',
            'quantity' => 'sometimes|integer|min:0|max:2147483647',
        ];
    }
}
