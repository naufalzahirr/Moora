@php
    $formId = $managedUser ? 'edit-user-'.$managedUser->id : 'add-user';
    $restoreForm = old('_form') === $formId;
    $formValue = fn ($key, $default = null) => $restoreForm ? old($key, $default) : $default;
    $errors = $restoreForm ? $errors : new \Illuminate\Support\ViewErrorBag;
@endphp
<input type="hidden" name="_form" value="{{ $formId }}">
<x-form-errors :bag="$errors" />
<div class="form-grid">
    <label class="field">NAMA<input name="name" value="{{ $formValue('name', $managedUser?->name) }}" required maxlength="255"></label>
    <label class="field">USERNAME<input name="username" value="{{ $formValue('username', $managedUser?->username) }}" required maxlength="100"></label>
    <label class="field">EMAIL <small>(opsional)</small><input type="email" name="email" value="{{ $formValue('email', $managedUser?->email) }}" maxlength="255"></label>
    <label class="field">PERAN<select name="role" required><option value="owner" @selected($formValue('role', $managedUser?->role) === 'owner')>Owner / Admin</option><option value="staff" @selected($formValue('role', $managedUser?->role ?? 'staff') === 'staff')>Petugas Persediaan</option></select></label>
    <label class="field">PASSWORD{{ $passwordRequired ? '' : ' (kosongkan jika tidak diubah)' }}<input type="password" name="password" autocomplete="new-password" @required($passwordRequired) minlength="8"></label>
    <label class="field">KONFIRMASI PASSWORD<input type="password" name="password_confirmation" autocomplete="new-password" @required($passwordRequired) minlength="8"></label>
    <input type="hidden" name="active" value="0"><label class="checkbox field full"><input type="checkbox" name="active" value="1" @checked($formValue('active', $managedUser?->active ?? true))> Akun aktif dan dapat masuk ke sistem</label>
</div>
