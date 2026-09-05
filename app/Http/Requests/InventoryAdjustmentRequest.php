<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'occurred_on.before_or_equal' => 'Tanggal stok opname tidak boleh melewati hari ini.',
        ];
    }
}
