<div class="form-grid">
    <label class="field">NAMA<input name="name" value="{{ old('name', $managedUser?->name) }}" required maxlength="255"></label>
    <label class="field">USERNAME<input name="username" value="{{ old('username', $managedUser?->username) }}" required maxlength="100"></label>
    <label class="field">EMAIL <small>(opsional)</small><input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" maxlength="255"></label>
    <label class="field">PERAN<select name="role" required><option value="owner" @selected(old('role', $managedUser?->role) === 'owner')>Owner / Admin</option><option value="staff" @selected(old('role', $managedUser?->role ?? 'staff') === 'staff')>Petugas Persediaan</option></select></label>
    <label class="field">PASSWORD{{ $passwordRequired ? '' : ' (kosongkan jika tidak diubah)' }}<input type="password" name="password" autocomplete="new-password" @required($passwordRequired) minlength="8"></label>
    <label class="field">KONFIRMASI PASSWORD<input type="password" name="password_confirmation" autocomplete="new-password" @required($passwordRequired) minlength="8"></label>
    <label class="checkbox field full"><input type="checkbox" name="active" value="1" @checked(old('active', $managedUser?->active ?? true))> Akun aktif dan dapat masuk ke sistem</label>
</div>
