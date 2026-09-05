@props(['bag'])
@if($bag->any())
<div class="form-alert" role="alert"><strong>Periksa isian berikut:</strong><ul>@foreach($bag->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
