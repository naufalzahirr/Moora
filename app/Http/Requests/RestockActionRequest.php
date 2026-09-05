<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestockActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'proposed', 'approved', 'skipped'])],
            'approved_quantity' => ['nullable', 'numeric', in_array($this->input('status'), ['proposed', 'approved'], true) ? 'gt:0' : 'min:0', 'required_unless:status,skipped'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('approved_quantity') === '') {
            $this->merge(['approved_quantity' => null]);
        }
    }
}
