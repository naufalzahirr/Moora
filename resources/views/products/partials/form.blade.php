@php
    $formId = $product ? 'edit-product-'.$product->id : 'add-product';
    $restoreForm = old('_form') === $formId;
    $formValue = fn ($key, $default = null) => $restoreForm ? old($key, $default) : $default;
    $errors = $restoreForm ? $errors : new \Illuminate\Support\ViewErrorBag;
@endphp
<input type="hidden" name="_form" value="{{ $formId }}">
<x-form-errors :bag="$errors" />
@php($quantityStep = $product?->quantityStep() ?? '1')
@php($minimumOrderStep = $product?->usesWholeUnits() === false ? '0.01' : '1')
<div class="form-grid">
    <label class="field">KODE BARANG<input name="code" value="{{ $formValue('code', $product?->code) }}" required maxlength="80">@error('code')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">NAMA BARANG<input name="name" value="{{ $formValue('name', $product?->name) }}" required maxlength="255">@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">SATUAN<input name="unit" value="{{ $formValue('unit', $product?->unit ?? 'pcs') }}" required maxlength="30" data-unit-input>@error('unit')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">KATEGORI<select name="category_id"><option value="">Tanpa kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($formValue('category_id', $product?->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label class="field full">SUPPLIER<select name="supplier_id"><option value="">Tanpa supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($formValue('supplier_id', $product?->supplier_id) == $supplier->id)>{{ $supplier->name }} · lead time {{ $supplier->lead_time_days }} hari</option>@endforeach</select><small>Lead time supplier dipakai untuk menghitung target stok operasional.</small></label>
    <label class="field">STOK MINIMUM<input type="number" min="0" step="{{ $quantityStep }}" name="minimum_stock" value="{{ $formValue('minimum_stock', $product?->minimum_stock ?? 0) }}" required data-unit-quantity><small>Isi lebih dari 0 agar peringatan stok rendah dapat bekerja.</small>@error('minimum_stock')<small class="field-error">{{ $message }}</small>@enderror</label>
</div>
<details class="advanced-settings" @if($errors->any()) open @endif><summary>Pengaturan restock lanjutan <small>Cadangan stok, target, dan aturan pemesanan</small></summary><div class="form-grid">
    <label class="field">STOK CADANGAN<input type="number" min="0" step="{{ $quantityStep }}" name="safety_stock" value="{{ $formValue('safety_stock', $product?->safety_stock ?? 0) }}" required data-unit-quantity><small>Cadangan untuk permintaan di luar perkiraan.</small>@error('safety_stock')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">TARGET STOK <small>(opsional)</small><input type="number" min="0" step="{{ $quantityStep }}" name="target_stock" value="{{ $formValue('target_stock', $product?->target_stock) }}" placeholder="Target minimum perencanaan" data-unit-quantity>@error('target_stock')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">JARAK ANTAR PEMESANAN (HARI)<input type="number" min="0" max="365" step="1" name="review_period_days" value="{{ $formValue('review_period_days', $product?->review_period_days ?? 7) }}" required>@error('review_period_days')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">MINIMUM PEMESANAN<input type="number" min="0" step="{{ $quantityStep }}" name="minimum_order_quantity" value="{{ $formValue('minimum_order_quantity', $product?->minimum_order_quantity ?? 0) }}" required data-unit-quantity>@error('minimum_order_quantity')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">KELIPATAN PESAN<input type="number" min="{{ $minimumOrderStep }}" step="{{ $quantityStep }}" name="order_multiple" value="{{ $formValue('order_multiple', $product?->order_multiple ?? 1) }}" required data-unit-quantity data-unit-positive><small>Contoh: isi 12 jika supplier hanya menerima pesanan per dus berisi 12 pcs.</small>@error('order_multiple')<small class="field-error">{{ $message }}</small>@enderror</label>
</div></details>
<div class="form-grid">
    <input type="hidden" name="active" value="0"><label class="checkbox field full"><input type="checkbox" name="active" value="1" @checked($formValue('active', $product?->active ?? true))> Barang aktif dan dapat digunakan pada data operasional berikutnya</label>
</div>
