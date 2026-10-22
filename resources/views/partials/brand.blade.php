@php $logo = ($companyProfile ?? null)?->logoUrl(); $bname = ($companyProfile ?? null)?->name ?? ($currentTenant->name ?? config('fnb.product_name')); @endphp
@if ($logo)<img src="{{ $logo }}" alt="Logo {{ $bname }}">@else<span class="logo-fallback">{{ mb_strtoupper(mb_substr($bname, 0, 1)) }}</span>@endif
