@extends('layouts.app')
@section('title', $account->exists ? 'Ubah Rekening' : 'Tambah Rekening')
@section('content')
{{-- @2026-Alifiandra --}}
<div class="page-head"><div><h1>{{ $account->exists ? 'Ubah rekening' : 'Tambah rekening' }}</h1><p>Bank, QRIS, e-wallet (OVO, GoPay, DANA, ShopeePay) atau kas. Pembayaran pesanan dengan metode yang sama otomatis masuk ke rekening ini.</p></div></div>
<div class="card card-pad" style="max-width:720px">
  @include('partials.flash')
  <form method="POST" action="{{ $account->exists ? route('finance.update', $account) : route('finance.store') }}">
    @csrf @if($account->exists) @method('PUT') @endif
    <div class="grid g2">
      <div class="field"><label for="name">Nama rekening</label><input id="name" name="name" value="{{ old('name', $account->name) }}" placeholder="mis. Bank BCA, OVO, QRIS" required maxlength="80"></div>
      <div class="field"><label for="method">Dipakai untuk metode</label>
        <select id="method" name="method">@foreach(config('fnb.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(old('method', $account->method) === $k)>{{ $l }}</option>@endforeach</select>
      </div>
      <div class="field"><label for="account_number">No. rekening / No. HP / ID merchant</label><input id="account_number" name="account_number" value="{{ old('account_number', $account->account_number) }}" maxlength="60" placeholder="opsional"></div>
      <div class="field"><label for="holder">Atas nama</label><input id="holder" name="holder" value="{{ old('holder', $account->holder) }}" maxlength="100" placeholder="opsional"></div>
      <div class="field"><label for="opening_balance">Saldo awal (Rp)</label><input id="opening_balance" type="number" step="1" name="opening_balance" value="{{ old('opening_balance', (float) $account->opening_balance) }}" required></div>
      <div class="field"><label for="opening_date">Saldo awal per tanggal</label><input id="opening_date" type="date" name="opening_date" value="{{ old('opening_date', $account->opening_date?->toDateString()) }}" required></div>
      <div class="field"><label for="branch_id">Cabang</label>
        <select id="branch_id" name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected((string) old('branch_id', $account->branch_id) === (string) $b->id)>{{ $b->name }}</option>@endforeach</select>
      </div>
      <div class="field"><label for="notes">Catatan</label><input id="notes" name="notes" value="{{ old('notes', $account->notes) }}" maxlength="200" placeholder="opsional"></div>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))> Aktif (bisa dipilih saat menerima pembayaran)</label></div>
    <p class="muted" style="font-size:13px">Jika satu metode punya lebih dari satu rekening aktif (mis. dua bank untuk Transfer), kasir memilih rekening tujuan saat menerima pembayaran.</p>
    <div class="row"><button class="btn btn-primary">Simpan</button><a href="{{ route('finance.index') }}" class="btn btn-ghost">Batal</a></div>
  </form>
</div>
@endsection
