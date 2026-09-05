<?php

namespace App\Http\Requests;

use App\Models\Period;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class DatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'staff'], true);
    }

    public function rules(): array
    {
        $presence = $this->input('next') === 'calculate' ? 'required' : 'nullable';

        return [
            'period_id' => ['required', 'exists:periods,id'],
            'next' => ['nullable', 'in:stay,calculate'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.sold_quantity' => [$presence, 'numeric', 'min:0'],
            'rows.*.sales_value' => [$presence, 'numeric', 'min:0'],
            'rows.*.ending_stock' => [$presence, 'numeric', 'min:0'],
            'rows.*.manual_yi' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows', []);
        if (! is_array($rows)) {
            return;
        }
        foreach ($rows as $productId => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['sales_value']) && is_string($row['sales_value'])) {
                // Accept local thousands separators without discarding signs or arbitrary text.
                $value = trim(str_replace(['Rp', ' '], '', $row['sales_value']));
                if (preg_match('/^[+-]?\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $value)) {
                    $value = str_replace('.', '', $value);
                }
                $rows[$productId]['sales_value'] = str_replace(',', '.', $value);
            }
        }

        $this->merge(['rows' => $rows]);
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $period = Period::find($this->integer('period_id'));
            $ids = $period->sales()->pluck('product_id');
            $products = Product::whereIn('id', array_keys($this->input('rows', [])))->get()->keyBy('id');
            foreach ($this->input('rows', []) as $id => $row) {
                if (! $ids->contains((int) $id)) {
                    $validator->errors()->add('rows', 'Barang yang dikirim tidak termasuk dalam data operasional ini.');

                    continue;
                }
                foreach (['sold_quantity', 'ending_stock'] as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && $value !== '' && $products[$id]->usesWholeUnits() && floor((float) $value) !== (float) $value) {
                        $validator->errors()->add("rows.{$id}.{$field}", "Jumlah {$products[$id]->name} harus berupa bilangan bulat.");
                    }
                }
            }
        }];
    }
}
