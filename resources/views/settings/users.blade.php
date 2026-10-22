@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')
<div class="page-head"><div><h1>Pengguna & hak akses</h1><p>Paket: maks {{ $currentTenant->max_users }} pengguna. Hak akses diberlakukan di server.</p></div></div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Nama</th><th>Peran</th><th>Cabang</th><th>Login terakhir</th></tr></thead><tbody>
    @foreach($users as $u)
      <tr>
        <td><b>{{ $u->name }}</b>@unless($u->is_active) <span class="badge badge-muted">Nonaktif</span>@endunless<br><small>{{ $u->email }}</small></td>
        <td>{{ $u->roleLabel() }}</td><td>{{ $u->branch?->name ?? 'Semua cabang' }}</td><td><small>{{ $u->last_login_at?->format('d/m/y H:i') ?? '—' }}</small></td>
      </tr>
    @endforeach
  </tbody></table>
  @foreach($users as $u)
    <details class="disclose" style="border-top:1px solid var(--border);padding:10px 18px"><summary>Ubah {{ $u->name }}</summary>
      <form method="POST" action="{{ route('settings.users.update', $u) }}" style="margin-top:10px">@csrf @method('PUT') @include('settings._user-fields', ['u' => $u])<button class="btn btn-primary btn-sm">Simpan</button></form>
    </details>
  @endforeach
  </div>
  <form method="POST" action="{{ route('settings.users.store') }}" class="card card-pad">@csrf
    <h3 style="margin-bottom:10px">Pengguna baru</h3>
    @include('settings._user-fields', ['u' => new \App\Models\User(['role' => 'cashier', 'is_active' => true])])
    <button class="btn btn-primary btn-block">Tambah</button>
  </form>
</div>
<div class="card card-pad" style="margin-top:16px">
  <h3 style="margin-bottom:8px">Ringkasan peran</h3>
  <table class="table"><tbody>@foreach($roles as $k => $r)<tr><td style="width:160px"><b>{{ $r['label'] }}</b></td><td><small>{{ in_array('*', $r['abilities']) ? 'Semua akses termasuk pengaturan' : implode(', ', $r['abilities']) }}</small></td></tr>@endforeach</tbody></table>
</div>
@endsection
