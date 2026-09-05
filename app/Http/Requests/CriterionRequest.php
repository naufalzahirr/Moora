<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        return [
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.type' => ['required', 'in:benefit,cost'],
            'criteria.*.weight' => ['required', 'numeric', 'min:0.000001', 'max:1'],
            'criteria.*.active' => ['nullable', 'boolean'],
        ];
    }
}
