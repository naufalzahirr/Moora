<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatasetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xls,xlsx'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas laporan penjualan wajib dipilih.',
            'file.file' => 'Unggahan laporan penjualan tidak valid.',
            'file.max' => 'Ukuran berkas laporan maksimal 10 MB.',
            'start_date.before_or_equal' => 'Tanggal awal tidak boleh melewati hari ini.',
            'end_date.before_or_equal' => 'Tanggal akhir tidak boleh melewati hari ini.',
            'file.mimes' => 'Format berkas harus CSV, XLS, atau XLSX.',
        ];
    }
}
