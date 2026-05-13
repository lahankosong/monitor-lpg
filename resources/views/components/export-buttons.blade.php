{{--
  Komponen tombol export — reusable di semua halaman
  
  Cara pakai:
  @include('components.export-buttons', [
    'type'        => 'transaksi',   // transaksi | nik | pangkalan
    'from'        => $from,
    'to'          => $to,
    'pangkalanId' => $pangkalanId ?? '',  // opsional
  ])
--}}

@php
  $baseUrl    = route('dashboard.export');
  $params     = array_filter([
    'type'         => $type ?? 'transaksi',
    'from'         => $from ?? now()->startOfMonth()->toDateString(),
    'to'           => $to   ?? now()->toDateString(),
    'pangkalan_id' => $pangkalanId ?? '',
  ]);
  $queryStr = http_build_query($params);
@endphp

<div class="export-group" style="display:flex;align-items:center;gap:6px;position:relative">
  <span style="font-size:12px;color:var(--muted)">Ekspor:</span>

  <a href="{{ $baseUrl }}?{{ $queryStr }}&format=csv"
     style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--text);text-decoration:none;background:var(--surface)"
     title="Download CSV">
    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    CSV
  </a>

  <a href="{{ $baseUrl }}?{{ $queryStr }}&format=xlsx"
     style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid #16a34a;border-radius:6px;font-size:12px;color:#16a34a;text-decoration:none;background:var(--surface)"
     title="Download Excel">
    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>
    XLSX
  </a>

  <a href="{{ $baseUrl }}?{{ $queryStr }}&format=pdf"
     style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid #dc2626;border-radius:6px;font-size:12px;color:#dc2626;text-decoration:none;background:var(--surface)"
     title="Download PDF"
     target="_blank">
    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    PDF
  </a>

  <a href="{{ $baseUrl }}?{{ $queryStr }}&format=jpg"
     style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid #7c3aed;border-radius:6px;font-size:12px;color:#7c3aed;text-decoration:none;background:var(--surface)"
     title="Export sebagai gambar"
     target="_blank">
    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    JPG
  </a>
</div>
