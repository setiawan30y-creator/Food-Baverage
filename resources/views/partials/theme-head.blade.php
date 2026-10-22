{{-- Tema tenant + mode (light/dark/system). @2026-Alifiandra --}}
@php
  $t = $theme ?? null;
  $mode = auth()->user()?->theme_mode ?: ($t->mode ?? 'system');
  $font = $t->font ?? 'Plus Jakarta Sans';
@endphp
@php
  $pwaTenant = $currentTenant ?? null;
  $pwaName = ($companyProfile ?? null)?->name ?? $pwaTenant?->name ?? 'ALMARA F&B';
  $pwaManifest = $manifestUrl ?? (($pwaTenant && request()->is('o/*')) ? route('pwa.customer', $pwaTenant->slug) : route('pwa.manifest'));
  $pwaIcon = ($pwaTenant && ($companyProfile ?? null)?->logo_path) ? route('pwa.icon', [$pwaTenant->id, 192]).'?v='.$companyProfile->updated_at?->timestamp : asset('icons/icon-192.png');
@endphp
<meta name="theme-color" content="{{ $t->primary ?? '#C2410C' }}">
<link rel="manifest" href="{{ $pwaManifest }}" crossorigin="use-credentials">
<link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ $pwaIcon }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit($pwaName, 14, '') }}">
@if($font !== 'System')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family={{ urlencode($font) }}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endif
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: config('fnb.version') }}">
<style>
:root {
  --primary: {{ $t->primary ?? '#C2410C' }}; --accent: {{ $t->accent ?? '#15803D' }};
  --bg: {{ $t->background ?? '#FAF7F2' }}; --surface: {{ $t->surface ?? '#FFFFFF' }};
  --text: {{ $t->text ?? '#1C1917' }}; --border: {{ $t->border ?? '#E7E0D6' }};
  --font: '{{ $font === 'System' ? 'system-ui' : $font }}', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
</style>
<script>
  (function () {
    var m = @js($mode);
    if (m === 'system') m = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-mode', m);
  })();
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) ?: config('fnb.version') }}"></script>
