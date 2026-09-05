<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'staff'], true);
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'code' => ['required', 'string', 'max:80', Rule::unique('products', 'code')->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'safety_stock' => ['required', 'numeric', 'min:0'],
            'target_stock' => ['nullable', 'numeric', 'gte:minimum_stock'],
            'review_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'minimum_order_quantity' => ['required', 'numeric', 'min:0'],
            'order_multiple' => ['required', 'numeric', 'gt:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! Product::isWholeUnit($this->input('unit'))) {
                return;
            }

            foreach (['minimum_stock', 'safety_stock', 'target_stock', 'minimum_order_quantity', 'order_multiple'] as $field) {
                $value = $this->input($field);
                if ($value === null || $value === '' || floor((float) $value) === (float) $value) {
                    continue;
                }

                $validator->errors()->add($field, 'Nilai untuk satuan '.strtoupper((string) $this->input('unit')).' harus berupa bilangan bulat.');
            }
        }];
    }
}
