@if (session('success'))<div class="alert alert-ok" role="status">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
@if ($errors->any())
  <div class="alert alert-danger" role="alert">
    @if ($errors->count() === 1) {{ $errors->first() }}
    @else <b>Periksa kembali isian:</b><ul style="margin:6px 0 0 18px;padding:0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
  </div>
@endif
