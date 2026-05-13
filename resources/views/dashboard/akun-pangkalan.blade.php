@extends('layouts.app')
@section('title', 'Kelola Akun Pangkalan')

@section('content')

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
  <div>
    <h1 class="text-lg font-semibold">Kelola Akun Pangkalan</h1>
    <p class="text-xs text-gray-500 mt-0.5">CRUD akun login + scraping data transaksi satu klik</p>
  </div>
  <form action="{{ route('dashboard.akun.import-json') }}" method="POST" class="inline">
    @csrf
    <button type="submit"
            onclick="return confirm('Sync semua password dari accounts.json ke database?')"
            class="text-sm border border-green-600 text-green-700 rounded-lg px-4 py-2 hover:bg-green-50">
      ↓ Sync dari accounts.json
    </button>
  </form>
  <button onclick="document.getElementById('modal-tambah').classList.remove('hidden')"
          class="text-sm bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700">
    + Tambah Akun
  </button>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 text-sm text-green-800">
  ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-800">
  @foreach($errors->all() as $e)✗ {{ $e }}<br>@endforeach
</div>
@endif

{{-- Status batch berjalan --}}
<div id="batchStatus" class="{{ $isRunning ? '' : 'hidden' }} bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
  <div class="flex items-center gap-3">
    <div class="animate-spin text-xl">⚙️</div>
    <div class="flex-1">
      <p class="font-medium text-sm text-blue-800">Batch scraping sedang berjalan...</p>
      <div class="mt-2 bg-blue-100 rounded-full h-2 overflow-hidden">
        <div id="progressBar" class="bg-blue-500 h-2 rounded-full transition-all duration-500"
             style="width: {{ isset($progress['total']) ? round($progress['current']/$progress['total']*100) : 0 }}%"></div>
      </div>
      <p id="progressText" class="text-xs text-blue-600 mt-1">
        {{ isset($progress['current']) ? "{$progress['current']}/{$progress['total']} — {$progress['label']}" : 'Memulai...' }}
      </p>
    </div>
    {{-- Tombol Stop — intercept via JS, tidak redirect halaman --}}
    <form id="stopForm" action="{{ route('dashboard.batch.stop') }}" method="POST">
      @csrf
      <button type="submit"
              class="text-xs bg-red-600 text-white rounded-lg px-3 py-2 hover:bg-red-700 whitespace-nowrap">
        ⏹ Stop
      </button>
    </form>
  </div>
</div>

{{-- Hasil batch terakhir --}}
@if($lastResult)
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-5">
  <p class="font-medium text-sm text-green-800 mb-2">✓ Batch scrape selesai</p>
  <div class="flex gap-6 text-sm">
    <span class="text-green-700">Berhasil: <strong>{{ $lastResult['berhasil'] }}</strong></span>
    <span class="text-red-600">Gagal: <strong>{{ $lastResult['gagal'] }}</strong></span>
    <span class="text-blue-700">Transaksi baru: <strong>{{ $lastResult['total_baru'] }}</strong></span>
  </div>
</div>
@endif

{{-- Bar scraping: [tanggal] [dropdown] [Scrape Pilihan] ........... [Scrape Semua] --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
  <div class="flex items-center gap-3 flex-wrap">

    {{-- Rentang tanggal: 1 bulan ini s/d hari ini --}}
    <input type="date" id="globalFrom"
           value="{{ now()->startOfMonth()->toDateString() }}"
           class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
    <span class="text-gray-400 text-xs">s/d</span>
    <input type="date" id="globalTo"
           value="{{ now()->toDateString() }}"
           class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">

    {{-- Dropdown pilih pangkalan --}}
    <select id="selectPangkalan"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[160px]">
      <option value="">— Pilih Pangkalan —</option>
      @foreach($akuns->where('is_active', true)->sortBy('label') as $a)
      <option value="{{ $a['id'] }}" data-label="{{ addslashes($a['label']) }}">
        {{ $a['label'] }}
      </option>
      @endforeach
    </select>

    {{-- Tombol scrape pilihan --}}
    <button onclick="scrapePilihan()"
            class="bg-blue-600 text-white rounded-lg px-4 py-1.5 text-sm hover:bg-blue-700 whitespace-nowrap"
            id="btnScrapePilihan">
      Scrape Pilihan
    </button>

    {{-- Tombol scrape semua — geser ke kanan --}}
    <form action="{{ route('dashboard.akun.scrape-all') }}" method="POST" class="ml-auto" id="formScrapeAll">
      @csrf
      <input type="hidden" name="from" id="hiddenFrom" value="{{ now()->startOfMonth()->toDateString() }}">
      <input type="hidden" name="to"   id="hiddenTo"   value="{{ now()->toDateString() }}">
      <button type="submit" id="btnScrapeAll"
              {{ $isRunning ? 'disabled' : '' }}
              class="bg-green-600 text-white rounded-lg px-4 py-1.5 text-sm hover:bg-green-700
                     disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
        🚀 Scrape Semua ({{ $akuns->where('is_active', true)->count() }} akun)
      </button>
    </form>

  </div>
</div>

{{-- Tabel akun --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Nama Pangkalan</th>
        <th class="text-left px-4 py-3">Username/Email</th>
        <th class="text-center px-4 py-3">Token</th>
        <th class="text-right px-4 py-3">Scrape Terakhir</th>
        <th class="text-right px-4 py-3">Txn</th>
        <th class="text-center px-4 py-3">Aktif</th>
        <th class="px-4 py-3 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($akuns as $a)
      <tr class="hover:bg-gray-50 {{ ! $a['is_active'] ? 'opacity-50' : '' }}" id="row-{{ $a['id'] }}">
        <td class="px-4 py-3 font-medium">
          {{ $a['label'] }}
          @if($a['registration_id'])
            <span class="text-xs text-gray-400 font-normal block">{{ $a['registration_id'] }}</span>
          @endif
        </td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ $a['username'] }}</td>
        <td class="px-4 py-3 text-center">
          @if($a['token_valid'])
            <span class="badge-aman">Aktif s/d {{ $a['token_expires'] }}</span>
          @else
            <span class="text-xs text-gray-400">—</span>
          @endif
        </td>
        <td class="px-4 py-3 text-right text-xs text-gray-500" id="scrape-info-{{ $a['id'] }}">
          @if($a['last_scrape'])
            {{ $a['last_scrape'] }}
            <span class="{{ $a['last_status']==='success' ? 'text-green-600':'text-red-500' }}">
              ({{ $a['last_status'] }})
            </span>
          @else
            <span class="text-gray-300">Belum pernah</span>
          @endif
        </td>
        <td class="px-4 py-3 text-right text-xs font-medium" id="txn-{{ $a['id'] }}">
          {{ $a['last_saved'] ?: '—' }}
        </td>
        <td class="px-4 py-3 text-center">
          <form action="{{ route('dashboard.akun.toggle', $a['id']) }}" method="POST" class="inline">
            @csrf @method('PATCH')
            <button type="submit"
                    class="text-xs {{ $a['is_active'] ? 'text-green-600' : 'text-gray-400' }} hover:underline">
              {{ $a['is_active'] ? '● Aktif' : '○ Nonaktif' }}
            </button>
          </form>
        </td>
        <td class="px-4 py-3 text-right">
          <div class="flex items-center justify-end gap-2">
            @if($a['is_active'])
            <button onclick="scrapeOne({{ $a['id'] }}, '{{ addslashes($a['label']) }}')"
                    class="text-xs bg-blue-600 text-white rounded px-3 py-1 hover:bg-blue-700 scrape-btn"
                    data-id="{{ $a['id'] }}">
              Scrape
            </button>
            @endif
            <a href="{{ route('dashboard.akun.edit', $a['id']) }}"
               class="text-xs text-gray-500 hover:text-gray-800">Edit</a>
            <form action="{{ route('dashboard.akun.destroy', $a['id']) }}" method="POST"
                  onsubmit="return confirm('Hapus akun {{ addslashes($a['label']) }}?')" class="inline">
              @csrf @method('DELETE')
              <button type="submit" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
          Belum ada akun. Klik "+ Tambah Akun" untuk mulai.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- Modal Tambah Akun --}}
<div id="modal-tambah" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md">
    <h2 class="font-semibold text-base mb-4">Tambah Akun Pangkalan</h2>
    <form action="{{ route('dashboard.akun.store') }}" method="POST">
      @csrf
      <div class="space-y-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Nama Pangkalan *</label>
          <input name="label" required autofocus
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
                 placeholder="mis: Pangkalan Bu Sari RT 05">
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Email / No HP *</label>
          <input name="username" required type="text"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
                 placeholder="email@gmail.com atau 081234567890">
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Password / PIN *</label>
          <input name="password" required type="password"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
                 placeholder="Password atau PIN MyPertamina">
        </div>
      </div>
      <div class="flex gap-2 mt-4">
        <button type="submit"
                class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm hover:bg-blue-700">Simpan</button>
        <button type="button"
                onclick="document.getElementById('modal-tambah').classList.add('hidden')"
                class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- Toast container --}}
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-2 pointer-events-none"></div>
{{-- Di halaman akun-pangkalan.blade.php --}}
@include('components.export-buttons', [
    'type' => 'transaksi',
    'from' => now()->startOfMonth()->toDateString(),
    'to'   => now()->toDateString(),
])
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

// ── Sync tanggal ke form hidden scrape-all ────────────────────
function syncDates() {
  document.getElementById('hiddenFrom').value = document.getElementById('globalFrom').value;
  document.getElementById('hiddenTo').value   = document.getElementById('globalTo').value;
}
document.getElementById('globalFrom').addEventListener('change', syncDates);
document.getElementById('globalTo').addEventListener('change', syncDates);

// ── Scrape pangkalan pilihan dari dropdown ────────────────────
async function scrapePilihan() {
  const sel   = document.getElementById('selectPangkalan');
  const id    = sel.value;
  const label = sel.selectedOptions[0]?.dataset.label || '';

  if (!id) { showToast('Pilih pangkalan dari dropdown terlebih dahulu', 'red'); return; }

  const from = document.getElementById('globalFrom').value;
  const to   = document.getElementById('globalTo').value;
  const btn  = document.getElementById('btnScrapePilihan');

  btn.disabled    = true;
  btn.textContent = '⏳ Scraping...';
  showToast(`Memulai scrape: ${label}`, 'blue');

  try {
    const res  = await fetch(`/dashboard/akun/${id}/scrape`, {
      method:  'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrfToken, 'Accept':'application/json' },
      body:    JSON.stringify({ from, to }),
    });
    const data = await res.json();

    if (data.success) {
      showPopup('✅ Scrape Selesai', `<strong>${label}</strong><br>${data.saved} transaksi baru disimpan.`);
      updateRow(id, data.saved);
    } else {
      showToast(`✗ ${label}: ${data.message}`, 'red');
    }
  } catch(e) {
    showToast(`✗ Error: ${e.message}`, 'red');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Scrape Pilihan';
  }
}

// ── Scrape via tombol Scrape di baris tabel ───────────────────
async function scrapeOne(id, label) {
  const btn  = document.querySelector(`.scrape-btn[data-id="${id}"]`);
  const from = document.getElementById('globalFrom').value;
  const to   = document.getElementById('globalTo').value;

  btn.disabled    = true;
  btn.textContent = '⏳';
  showToast(`Scraping: ${label}`, 'blue');

  try {
    const res  = await fetch(`/dashboard/akun/${id}/scrape`, {
      method:  'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrfToken, 'Accept':'application/json' },
      body:    JSON.stringify({ from, to }),
    });
    const data = await res.json();

    if (data.success) {
      btn.textContent = '✓';
      btn.classList.replace('bg-blue-600','bg-green-600');
      showToast(`✓ ${label}: ${data.saved} transaksi`, 'green');
      updateRow(id, data.saved);
    } else {
      btn.textContent = '✗';
      btn.classList.replace('bg-blue-600','bg-red-500');
      showToast(`✗ ${label}: ${data.message}`, 'red');
    }
  } catch(e) {
    btn.textContent = '✗';
    showToast(`✗ ${e.message}`, 'red');
  } finally {
    setTimeout(() => {
      btn.disabled    = false;
      btn.textContent = 'Scrape';
      btn.classList.remove('bg-green-600','bg-red-500');
      btn.classList.add('bg-blue-600');
    }, 4000);
  }
}

// Update baris tabel setelah scrape berhasil
function updateRow(id, saved) {
  const txnEl    = document.getElementById(`txn-${id}`);
  const infoEl   = document.getElementById(`scrape-info-${id}`);
  if (txnEl)  txnEl.textContent  = saved || '—';
  if (infoEl) infoEl.innerHTML   = '<span class="text-green-600">Baru saja (success)</span>';
}

// ── Tombol Stop — pakai fetch + popup, bukan redirect ─────────
document.getElementById('stopForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  if (!confirm('Hentikan batch scraping yang sedang berjalan?')) return;

  try {
    const res  = await fetch('{{ route("dashboard.batch.stop") }}', {
      method:  'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
      showPopup('⏹ Scraping Dihentikan',
        'Batch scraping berhasil dihentikan.<br>Data pangkalan yang sudah selesai tetap tersimpan.');
      document.getElementById('batchStatus').classList.add('hidden');
      document.getElementById('btnScrapeAll').disabled = false;
    }
  } catch(e) {
    showToast('Gagal menghentikan scraping', 'red');
  }
});

// ── Toast (notifikasi kecil pojok kanan bawah) ────────────────
function showToast(msg, color = 'green') {
  const cls = { green:'bg-green-600', red:'bg-red-600', blue:'bg-blue-600' };
  const el  = document.createElement('div');
  el.className    = `px-4 py-3 rounded-xl shadow-lg text-sm font-medium text-white pointer-events-auto ${cls[color]??'bg-gray-700'}`;
  el.textContent  = msg;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

// ── Popup modal ───────────────────────────────────────────────
function showPopup(title, body) {
  const overlay = document.createElement('div');
  overlay.className = 'fixed inset-0 bg-black/40 flex items-center justify-center z-50';
  overlay.innerHTML = `
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm mx-4 text-center">
      <h3 class="font-semibold text-base mb-2">${title}</h3>
      <p class="text-sm text-gray-500 mb-5">${body}</p>
      <button onclick="this.closest('.fixed').remove()"
              class="bg-gray-800 text-white rounded-lg px-6 py-2 text-sm hover:bg-gray-700">OK</button>
    </div>`;
  document.body.appendChild(overlay);
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

// ── Polling status batch ──────────────────────────────────────
@if($isRunning)
function pollStatus() {
  fetch('{{ route("dashboard.akun.status") }}')
    .then(r => r.json())
    .then(data => {
      if (data.running) {
        document.getElementById('batchStatus').classList.remove('hidden');
        if (data.progress?.total) {
          document.getElementById('progressBar').style.width =
            Math.round(data.progress.current / data.progress.total * 100) + '%';
          document.getElementById('progressText').textContent =
            `${data.progress.current}/${data.progress.total} — ${data.progress.label}`;
        }
      } else {
        window.location.reload();
      }
    }).catch(() => {});
}
setInterval(pollStatus, 3000);
pollStatus();
@endif
</script>
@endpush