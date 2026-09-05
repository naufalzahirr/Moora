<div class="form-grid">
    <label class="field full">NAMA SUPPLIER<input name="name" value="{{ old('name', $supplier?->name) }}" maxlength="255" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">NOMOR KONTAK<input name="phone" value="{{ old('phone', $supplier?->phone) }}" maxlength="30">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field">LEAD TIME (HARI)<input type="number" min="0" max="365" name="lead_time_days" value="{{ old('lead_time_days', $supplier?->lead_time_days ?? 7) }}" required>@error('lead_time_days')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="field full">ALAMAT <small>(opsional)</small><textarea name="address" maxlength="2000">{{ old('address', $supplier?->address) }}</textarea>@error('address')<small class="field-error">{{ $message }}</small>@enderror</label>
    <label class="checkbox field full"><input type="checkbox" name="active" value="1" @checked(old('active', $supplier?->active ?? true))> Supplier aktif dan dapat dipilih untuk barang baru</label>
</div>
