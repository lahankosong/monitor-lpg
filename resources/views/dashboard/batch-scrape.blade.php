@extends('layouts.app')
@section('title', 'Batch Scrape')

@section('content')

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
  <div>
    <h1 class="text-lg font-semibold">Batch Scrape — {{ count($accounts) }} Pangkalan</h1>
    <p class="text-xs text-gray-500 mt-0.5">
      Login otomatis ke semua pangkalan via Playwright, scrape data sekaligus.
    </p>
  </div>
</div>

{{-- Status sistem --}}
<div class="grid grid-cols-2 gap-4 mb-5">
  <div class="bg-white border rounded-xl p-4 flex items-center gap-3">
    <div class="text-2xl">{{ $hasScript ? '✅' : '❌' }}</div>
    <div>
      <p class="font-medium text-sm">Script Playwright</p>
      <p class="text-xs text-gray-400">
        {{ $hasScript ? 'auto_login_batch.py tersedia' : 'Script tidak ditemukan di scripts/' }}
      </p>
    </div>
  </div>
  <div class="bg-white border rounded-xl p-4 flex items-center gap-3">
    <div class="text-2xl">{{ $hasAccounts ? '✅' : '❌' }}</div>
    <div>
      <p class="font-medium text-sm">accounts.json</p>
      <p class="text-xs text-gray-400">
        {{ $hasAccounts ? count($accounts).' akun terdaftar' : 'Belum ada akun' }}
      </p>
    </div>
  </div>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-5 text-sm text-green-800">
  ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 text-sm text-red-800">
  @foreach($errors->all() as $e) ✗ {{ $e }}<br> @endforeach
</div>
@endif

<div class="grid md:grid-cols-2 gap-5 mb-5">

  {{-- Form jalankan batch --}}
  <div class="bg-white rounded-xl border border-gray-200 p-5">
    <h2 class="font-medium text-sm mb-1">Jalankan Batch Scrape</h2>
    <p class="text-xs text-gray-400 mb-4">
      Playwright akan login ke {{ count($accounts) }} pangkalan satu per satu,
      ambil token, lalu scrape data transaksi otomatis.
      Estimasi waktu: ~{{ count($accounts) * 30 }} detik.
    </p>
    <form action="{{ route('dashboard.batch.run') }}" method="POST" id="formBatch">
      @csrf
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Dari</label>
          <input type="date" name="from" value="{{ now()->startOfWeek()->toDateString() }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Sampai</label>
          <input type="date" name="to" value="{{ now()->toDateString() }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>
      </div>
      <button type="submit" id="btnBatch"
              {{ (! $hasScript || ! $hasAccounts) ? 'disabled' : '' }}
              class="w-full bg-blue-600 text-white rounded-lg px-4 py-2.5 text-sm font-medium
                     hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
        🚀 Jalankan Batch Scrape Semua Pangkalan
      </button>
      <p id="loadingMsg" class="text-xs text-gray-400 mt-2 hidden text-center">
        ⏳ Sedang login dan scraping {{ count($accounts) }} pangkalan... Mohon tunggu, jangan tutup halaman ini.
      </p>
    </form>
  </div>

  {{-- Form edit accounts.json --}}
  <div class="bg-white rounded-xl border border-gray-200 p-5">
    <h2 class="font-medium text-sm mb-1">Kelola Akun Pangkalan</h2>
    <p class="text-xs text-gray-400 mb-3">
      Edit daftar akun dalam format JSON. Setiap akun harus punya
      <code class="bg-gray-100 px-1 rounded">label</code>,
      <code class="bg-gray-100 px-1 rounded">email</code>, dan
      <code class="bg-gray-100 px-1 rounded">pin</code>.
    </p>
    <form action="{{ route('dashboard.batch.accounts') }}" method="POST">
      @csrf
      <textarea name="accounts_json" rows="8"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono
                       focus:outline-none focus:border-blue-500 resize-none"
      >{{ json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
      <button type="submit"
              class="mt-2 border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50 w-full">
        Simpan Daftar Akun
      </button>
    </form>
  </div>

</div>

{{-- Daftar akun --}}
@if($hasAccounts)
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-5">
  <div class="px-4 py-3 border-b border-gray-100">
    <h2 class="font-medium text-sm">Daftar Akun ({{ count($accounts) }} pangkalan)</h2>
  </div>
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
      <tr>
        <th class="text-left px-4 py-2">#</th>
        <th class="text-left px-4 py-2">Label</th>
        <th class="text-left px-4 py-2">Email</th>
        <th class="text-center px-4 py-2">PIN</th>
        <th class="text-right px-4 py-2">Scrape Terakhir</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @foreach($accounts as $i => $acc)
      @php
        $session = \App\Models\PangkalanSession::where('username', $acc['email'])->first();
        $lastLog = $session
          ? \App\Models\ScrapeLog::where('pangkalan_id', $session->pangkalan_id)->latest('scraped_at')->first()
          : null;
      @endphp
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-2.5 text-gray-400">{{ $i + 1 }}</td>
        <td class="px-4 py-2.5 font-medium">{{ $acc['label'] ?? '—' }}</td>
        <td class="px-4 py-2.5 text-gray-600">{{ $acc['email'] }}</td>
        <td class="px-4 py-2.5 text-center text-gray-400">••••••</td>
        <td class="px-4 py-2.5 text-right text-xs text-gray-400">
          @if($lastLog)
            {{ $lastLog->scraped_at->format('d/m H:i') }}
            <span class="{{ $lastLog->status === 'success' ? 'text-green-600' : 'text-red-500' }}">
              ({{ $lastLog->records_saved }} txn)
            </span>
          @else
            <span class="text-gray-300">Belum pernah</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- Log scraping terbaru --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
    <h2 class="font-medium text-sm">Log Scraping Terbaru</h2>
    <a href="{{ route('dashboard.nik.list') }}" class="text-xs text-blue-600 hover:underline">
      Lihat data NIK →
    </a>
  </div>
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
      <tr>
        <th class="text-left px-4 py-2">Pangkalan</th>
        <th class="text-left px-4 py-2">Periode</th>
        <th class="text-center px-4 py-2">Status</th>
        <th class="text-right px-4 py-2">Diambil</th>
        <th class="text-right px-4 py-2">Disimpan</th>
        <th class="text-right px-4 py-2">Waktu</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($lastLogs as $log)
      @php
        $token = \App\Models\PangkalanToken::where('pangkalan_id', $log->pangkalan_id)->first();
      @endphp
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-2.5 font-medium">
          {{ $token?->label ?? substr($log->pangkalan_id ?? '—', 0, 12) }}
        </td>
        <td class="px-4 py-2.5 text-xs text-gray-500">
          {{ $log->start_date->format('d/m') }} s/d {{ $log->end_date->format('d/m') }}
        </td>
        <td class="px-4 py-2.5 text-center">
          @if($log->status === 'success')
            <span class="badge-aman">Berhasil</span>
          @else
            <span class="badge-alert">Gagal</span>
          @endif
        </td>
        <td class="px-4 py-2.5 text-right text-xs">{{ $log->records_fetched }}</td>
        <td class="px-4 py-2.5 text-right text-xs font-medium">{{ $log->records_saved }}</td>
        <td class="px-4 py-2.5 text-right text-xs text-gray-400">
          {{ $log->scraped_at->format('d/m H:i') }}
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-xs">
          Belum ada log scraping.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('formBatch').addEventListener('submit', function() {
  document.getElementById('btnBatch').disabled = true;
  document.getElementById('loadingMsg').classList.remove('hidden');
});
</script>
@endpush
