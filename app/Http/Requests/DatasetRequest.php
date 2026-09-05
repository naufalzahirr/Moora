<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'exists:periods,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.sold_quantity' => ['required', 'numeric', 'min:0'],
            'rows.*.sales_value' => ['required', 'numeric', 'min:0'],
            'rows.*.ending_stock' => ['required', 'numeric', 'min:0'],
            'rows.*.manual_yi' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows', []);
        foreach ($rows as $productId => $row) {
            if (isset($row['sales_value']) && is_string($row['sales_value'])) {
                $rows[$productId]['sales_value'] = preg_replace('/[^0-9]/', '', $row['sales_value']);
            }
        }

        $this->merge(['rows' => $rows]);
    }
}
