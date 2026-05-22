@extends('layouts.app')
@section('title', 'Status Batch Scrape')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;
            flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Status Batch Scraping</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Progress login & scraping pangkalan via Playwright
    </p>
  </div>
  <a href="{{ route('dashboard.batch.index') }}"
     style="border:1px solid var(--border);color:var(--text);background:var(--surface);
            border-radius:8px;padding:7px 14px;font-size:13px;text-decoration:none">
    ← Kembali
  </a>
</div>

@if(session('success'))
<div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:10px;
            padding:12px 16px;margin-bottom:16px;font-size:13px;color:#065F46;font-weight:500">
  ✓ {{ session('success') }}
</div>
@endif

{{-- Status card --}}
<div class="card" style="padding:28px;margin-bottom:16px;text-align:center">
  @if($isRunning)
  <div style="font-size:40px;margin-bottom:12px;animation:spin 2s linear infinite">⚙️</div>
  <p style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">Sedang Berjalan...</p>
  <p style="font-size:12px;color:var(--muted);margin-bottom:20px">
    Playwright sedang login dan scraping pangkalan.
    @if(!empty($params['started_at']))
      Dimulai: {{ \Carbon\Carbon::parse($params['started_at'])->format('H:i:s') }}
    @endif
  </p>

  <div style="background:var(--border);border-radius:99px;height:12px;overflow:hidden;margin-bottom:8px">
    <div id="progressFill"
         style="background:var(--accent);height:12px;border-radius:99px;
                width:0%;transition:width .5s ease"></div>
  </div>
  <p id="progressText" style="font-size:12px;color:var(--muted)">Menunggu update...</p>

  @else
    @if($lastResult)
    <div style="font-size:40px;margin-bottom:12px">✅</div>
    <p style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:16px">Selesai!</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
      <div style="background:var(--bg);border-radius:10px;padding:14px">
        <p style="font-size:28px;font-weight:700;color:var(--stat-positive,#059669)">
          {{ $lastResult['berhasil'] ?? 0 }}
        </p>
        <p style="font-size:11px;color:var(--muted);margin-top:2px">Berhasil</p>
      </div>
      <div style="background:var(--bg);border-radius:10px;padding:14px">
        <p style="font-size:28px;font-weight:700;color:var(--stat-negative,#DC2626)">
          {{ $lastResult['gagal'] ?? 0 }}
        </p>
        <p style="font-size:11px;color:var(--muted);margin-top:2px">Gagal</p>
      </div>
      <div style="background:var(--bg);border-radius:10px;padding:14px">
        <p style="font-size:28px;font-weight:700;color:var(--accent)">
          {{ $lastResult['total_baru'] ?? 0 }}
        </p>
        <p style="font-size:11px;color:var(--muted);margin-top:2px">Transaksi Baru</p>
      </div>
    </div>
    @else
    <div style="font-size:40px;margin-bottom:12px">💤</div>
    <p style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">
      Tidak ada proses berjalan
    </p>
    <p style="font-size:12px;color:var(--muted)">
      Jalankan batch scrape dari halaman sebelumnya.
    </p>
    @endif
  @endif
</div>

{{-- Alternatif terminal --}}
<div class="card" style="padding:16px 18px;margin-bottom:16px;background:var(--bg)">
  <p style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px">
    💡 Alternatif: Jalankan via Terminal
  </p>
  <p style="font-size:12px;color:var(--muted);margin-bottom:10px">
    Lebih reliable untuk proses panjang. Buka terminal baru dan jalankan:
  </p>
  <div style="background:#0F172A;border-radius:8px;padding:12px 14px;
              font-family:monospace;font-size:12px;line-height:1.8">
    <p style="color:#64748B"># Mode berat — login via Playwright (perlu Python):</p>
    <p style="color:#4ADE80">php artisan batch:scrape \
      --from={{ now()->startOfWeek()->toDateString() }} \
      --to={{ now()->toDateString() }}</p>
    <p style="color:#64748B;margin-top:8px"># Mode ringan — pakai token tersimpan (direkomendasikan):</p>
    <p style="color:#4ADE80">php artisan scrape:transaksi \
      --from={{ now()->startOfWeek()->toDateString() }} \
      --to={{ now()->toDateString() }}</p>
    <p style="color:#64748B;margin-top:8px"># Cek status semua token:</p>
    <p style="color:#4ADE80">php artisan token:status</p>
  </div>
  <p style="font-size:11px;color:var(--muted);margin-top:8px">
    Proses berjalan di background, hasilnya otomatis masuk ke database. Tidak ada timeout PHP.
  </p>
</div>

{{-- Tombol navigasi --}}
<div style="display:flex;gap:10px">
  <a href="{{ route('dashboard.nik.list') }}"
     style="flex:1;text-align:center;background:var(--accent);color:#fff;
            border-radius:8px;padding:10px;font-size:13px;font-weight:600;text-decoration:none">
    Lihat Data NIK →
  </a>
  <a href="{{ route('dashboard.index') }}"
     style="flex:1;text-align:center;border:1px solid var(--border);color:var(--text);
            background:var(--surface);border-radius:8px;padding:10px;font-size:13px;text-decoration:none">
    Dashboard
  </a>
</div>

@endsection

@push('scripts')
<style>
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
</style>
@if($isRunning)
<script>
function pollStatus() {
  fetch('{{ route("dashboard.batch.status-api") }}')
    .then(r => r.json())
    .then(data => {
      if (data.progress && data.progress.total) {
        const pct = Math.round(data.progress.current / data.progress.total * 100);
        document.getElementById('progressFill').style.width = pct + '%';
        document.getElementById('progressText').textContent =
          `${data.progress.current}/${data.progress.total} pangkalan — ${data.progress.label ?? ''}`;
      }
      if (!data.running) window.location.reload();
    })
    .catch(() => {});
}
setInterval(pollStatus, 5000);
pollStatus();
</script>
@endif
@endpush
