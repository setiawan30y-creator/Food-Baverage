<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="author" content="@2026-Alifiandra">
<title>QR Meja · {{ $currentBranch->name }}</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  @page { size: A4; margin: 10mm; }
  body { font-family: system-ui, sans-serif; margin: 0; color: #111; }
  .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8mm; }
  .card { border: 1.5px solid #222; border-radius: 4mm; padding: 6mm; text-align: center; break-inside: avoid; }
  .qr { display: flex; justify-content: center; margin: 4mm 0; }
  h2 { margin: 0; font-size: 20pt; } p { margin: 1mm 0; font-size: 10pt; }
  .bar { text-align: center; padding: 10px; } .bar button { padding: 8px 16px; font-size: 14px; }
  @media print { .bar { display: none; } }
</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Cetak</button></div>
<div class="grid">
  @foreach($tables as $t)
    <div class="card">
      <p><b>{{ $companyProfile->name ?? '' }}</b></p>
      <h2>{{ $t->name }}</h2>
      <div class="qr" data-url="{{ route('qr.show', $t->qr_token) }}"></div>
      <p>Pindai untuk lihat menu & pesan</p>
      <p style="font-size:8pt;color:#666">{{ $currentBranch->name }}</p>
    </div>
  @endforeach
</div>
<script>
document.querySelectorAll('.qr').forEach(el => new QRCode(el, { text: el.dataset.url, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M }));
</script>
</body>
</html>
