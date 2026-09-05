<?php

return [
    'accepted' => ':attribute harus diterima.',
    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date.',
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa berkas.',
    'in' => ':attribute yang dipilih tidak valid.',
    'max' => [
        'array' => ':attribute tidak boleh memiliki lebih dari :max item.',
        'file' => 'Ukuran :attribute maksimal :max kilobita.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal memiliki :min item.',
        'file' => 'Ukuran :attribute minimal :min kilobita.',
        'numeric' => ':attribute minimal bernilai :min.',
        'string' => ':attribute minimal memiliki :min karakter.',
    ],
    'mimes' => ':attribute harus berupa berkas berjenis: :values.',
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'attributes' => [
        'code' => 'kode barang',
        'end_date' => 'tanggal akhir',
        'file' => 'berkas laporan',
        'name' => 'nama',
        'period_id' => 'periode',
        'start_date' => 'tanggal awal',
        'username' => 'username',
    ],
];
