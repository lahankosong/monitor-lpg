@extends('layouts.app')
@section('title', 'Status Batch Scrape')

@section('content')

<div class="max-w-2xl mx-auto">

  <div class="mb-5 flex items-center justify-between">
    <h1 class="text-lg font-semibold">Status Batch Scraping</h1>
    <a href="{{ route('dashboard.batch.index') }}"
       class="text-xs text-gray-400 hover:text-gray-700">← Kembali</a>
  </div>

  @if(session('success'))
  <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-5 text-sm text-green-800">
    ✓ {{ session('success') }}
  </div>
  @endif

  {{-- Status card --}}
  <div id="statusCard" class="bg-white rounded-xl border border-gray-200 p-6 mb-5 text-center">
    @if($isRunning)
    <div class="text-4xl mb-3 animate-pulse">⚙️</div>
    <p class="font-semibold text-base mb-1">Sedang Berjalan...</p>
    <p class="text-xs text-gray-500 mb-4">
      Playwright sedang login dan scraping pangkalan.
      @if(!empty($params['started_at']))
        Dimulai: {{ \Carbon\Carbon::parse($params['started_at'])->format('H:i:s') }}
      @endif
    </p>

    <div id="progressBar" class="bg-gray-100 rounded-full h-3 mb-2 overflow-hidden">
      <div id="progressFill" class="bg-blue-500 h-3 rounded-full transition-all duration-500"
           style="width: 0%"></div>
    </div>
    <p id="progressText" class="text-xs text-gray-400">Menunggu update...</p>

    @else
    @if($lastResult)
    <div class="text-4xl mb-3">✅</div>
    <p class="font-semibold text-base mb-1">Selesai!</p>
    <div class="grid grid-cols-3 gap-4 mt-4 text-center">
      <div>
        <p class="text-2xl font-semibold text-green-600">{{ $lastResult['berhasil'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">Berhasil</p>
      </div>
      <div>
        <p class="text-2xl font-semibold text-red-500">{{ $lastResult['gagal'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">Gagal</p>
      </div>
      <div>
        <p class="text-2xl font-semibold text-blue-600">{{ $lastResult['total_baru'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">Transaksi baru</p>
      </div>
    </div>
    @else
    <div class="text-4xl mb-3">💤</div>
    <p class="font-semibold text-base mb-1">Tidak ada proses berjalan</p>
    <p class="text-xs text-gray-400">Jalankan batch scrape dari halaman sebelumnya.</p>
    @endif
    @endif
  </div>

  {{-- Cara jalankan via terminal (alternatif) --}}
  <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-5">
    <p class="font-medium text-sm mb-2">💡 Alternatif: Jalankan via Terminal</p>
    <p class="text-xs text-gray-500 mb-2">
      Lebih reliable untuk proses panjang — buka terminal baru dan jalankan:
    </p>
    <div class="bg-gray-800 text-green-400 rounded-lg p-3 font-mono text-xs">
      <p># Dari folder project Laravel:</p>
      <p>php artisan batch:scrape --from={{ now()->startOfWeek()->toDateString() }} --to={{ now()->toDateString() }}</p>
    </div>
    <p class="text-xs text-gray-400 mt-2">
      Proses berjalan di terminal, hasilnya otomatis masuk ke database.
      Tidak ada timeout PHP.
    </p>
  </div>

  <div class="flex gap-3">
    <a href="{{ route('dashboard.nik.list') }}"
       class="flex-1 text-center bg-blue-600 text-white rounded-lg px-4 py-2 text-sm hover:bg-blue-700">
      Lihat Data NIK →
    </a>
    <a href="{{ route('dashboard.index') }}"
       class="flex-1 text-center border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">
      Dashboard
    </a>
  </div>

</div>

@endsection

@push('scripts')
@if($isRunning)
<script>
// Poll status setiap 5 detik saat proses berjalan
function pollStatus() {
  fetch('{{ route("dashboard.batch.status-api") }}')
    .then(r => r.json())
    .then(data => {
      if (data.progress && data.progress.total) {
        const pct = Math.round(data.progress.current / data.progress.total * 100);
        document.getElementById('progressFill').style.width = pct + '%';
        document.getElementById('progressText').textContent =
          `${data.progress.current}/${data.progress.total} pangkalan — ${data.progress.label}`;
      }

      if (! data.running) {
        // Selesai — reload halaman
        window.location.reload();
      }
    })
    .catch(() => {});
}

setInterval(pollStatus, 5000);
pollStatus();
</script>
@endif
@endpush
