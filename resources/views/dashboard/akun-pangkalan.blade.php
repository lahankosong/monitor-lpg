@extends('layouts.app')
@section('title', 'Kelola Akun Pangkalan')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Kelola Akun Pangkalan</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Credentials diambil dari database — satu sumber untuk scraping manual maupun GitHub Actions
    </p>
  </div>
  <button onclick="document.getElementById('modal-tambah').classList.remove('hidden')"
          style="background:var(--accent);color:#151F28;border:none;border-radius:8px;
                 padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">
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

{{-- Hasil batch terakhir --}}
@if($lastResult)
<div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:12px;padding:14px 18px;margin-bottom:16px">
  <p style="font-size:13px;font-weight:600;color:#065F46;margin-bottom:6px">✓ Batch scrape selesai</p>
  <div style="display:flex;gap:20px;font-size:13px;flex-wrap:wrap">
    <span style="color:#059669">Berhasil: <strong>{{ $lastResult['berhasil'] }}</strong></span>
    <span style="color:#DC2626">Gagal: <strong>{{ $lastResult['gagal'] }}</strong></span>
    <span style="color:#1D4ED8">Transaksi baru: <strong>{{ $lastResult['total_baru'] }}</strong></span>
  </div>
</div>
@endif

{{-- POPUP PROGRESS — muncul floating saat scraping berjalan --}}
<div id="popup-progress" style="display:{{ $isRunning ? 'flex' : 'none' }};
     position:fixed;bottom:20px;right:20px;z-index:500;
     flex-direction:column;
     background:var(--surface);border:1px solid var(--border);
     border-radius:14px;width:360px;
     box-shadow:0 8px 32px rgba(0,0,0,.3);overflow:hidden">

  {{-- Header popup --}}
  <div style="padding:12px 16px;background:rgba(41,253,83,.08);border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <div style="display:flex;align-items:center;gap:8px">
      <span id="pp-spinner" style="font-size:16px;animation:spin 1s linear infinite">⚙</span>
      <span style="font-size:13px;font-weight:600;color:var(--text)">Batch Scraping Berjalan</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <span id="pp-waktu" style="font-size:11px;color:var(--muted)"></span>
      <form action="{{ route('dashboard.batch.stop') }}" method="POST" style="margin:0">
        @csrf
        <button type="submit"
                style="background:#DC2626;color:#fff;border:none;border-radius:6px;
                       padding:3px 10px;font-size:11px;font-weight:600;cursor:pointer">
          ⏹ Stop
        </button>
      </form>
    </div>
  </div>

  {{-- Progress bar --}}
  <div style="padding:10px 16px;border-bottom:1px solid var(--border)">
    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:5px">
      <span id="pp-label">Memulai...</span>
      <span id="pp-pct">0%</span>
    </div>
    <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
      <div id="pp-bar" style="height:6px;background:var(--accent);border-radius:3px;
                               width:0%;transition:width .4s ease"></div>
    </div>
    <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:right">
      <span id="pp-count">0/0</span> pangkalan
    </div>
  </div>

  {{-- Log per pangkalan --}}
  <div id="pp-log"
       style="max-height:200px;overflow-y:auto;padding:8px 0;font-size:11px;font-family:monospace">
    <div class="log-waiting" style="padding:8px 16px;color:var(--muted);font-size:11px">⏳ Menunggu proses dimulai...</div>
  </div>

  {{-- Footer waktu --}}
  <div style="padding:8px 16px;border-top:1px solid var(--border);
              display:flex;justify-content:space-between;font-size:10px;color:var(--muted)">
    <span>Mulai: <span id="pp-start-time">—</span></span>
    <span>Durasi: <span id="pp-duration">0 detik</span></span>
  </div>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
#pp-log .log-ok   { color: var(--accent); }
#pp-log .log-fail { color: #FF4D4F; }
#pp-log .log-info { color: var(--muted); }
</style>

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
{{-- Modal Edit Credentials --}}
<div id="modal-edit-akun" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeEditAkun()">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:440px"
       onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);
                display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Edit Kredensial</h3>
        <p id="edit-akun-sub" style="font-size:11px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button onclick="closeEditAkun()"
              style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-edit-akun" method="POST" style="padding:18px 20px">
      @csrf @method('PUT')
      <div style="display:flex;flex-direction:column;gap:12px">

        <div>
          <label class="flabel">Nama Pangkalan *</label>
          <input name="label" id="ea-label" required class="finput">
        </div>

        <div>
          <label class="flabel">Email / No HP *</label>
          <input name="username" id="ea-username" required type="text" class="finput"
                 placeholder="email@gmail.com atau 081234567890">
        </div>

        <div>
          <label class="flabel">
            Password / PIN
            <span style="font-weight:400;color:var(--muted)">(kosongkan jika tidak diubah)</span>
          </label>
          <div style="position:relative">
            <input name="password" type="password" id="ea-password" class="finput"
                   placeholder="••••••••" style="padding-right:70px">
            <button type="button" onclick="toggleEaPassword()"
                    style="position:absolute;right:8px;top:50%;transform:translateY(-50%);
                           background:none;border:1px solid var(--border);color:var(--muted);
                           border-radius:6px;padding:2px 8px;font-size:11px;cursor:pointer">
              Lihat
            </button>
          </div>
          <p id="ea-pin-info" style="font-size:11px;color:var(--muted);margin-top:4px;display:none"></p>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" id="ea-active" value="1"
                 style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
          <label for="ea-active" style="font-size:13px;color:var(--text);cursor:pointer">
            Akun aktif
          </label>
        </div>
      </div>

      <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit"
                style="flex:1;background:var(--accent);color:#151F28;border:none;
                       border-radius:8px;padding:10px;font-size:13px;font-weight:600;cursor:pointer">
          Simpan
        </button>
        <button type="button" onclick="closeEditAkun()"
                style="border:1px solid var(--border);background:var(--surface);color:var(--text);
                       border-radius:8px;padding:10px 16px;font-size:13px;cursor:pointer">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

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

// ── Popup progress polling ────────────────────────────────────
let scrapeStartTime = null;
let durationInterval = null;
let lastLabel = '';

let lastLogCount = 0;

function renderLogs(logs, extraLine = null) {
  const logEl = document.getElementById('pp-log');

  // Hanya render baris baru (tidak re-render semua)
  const existingCount = logEl.querySelectorAll('.log-entry').length;
  const newLogs = logs.slice(existingCount);

  // Hapus pesan "Menunggu log..." jika ada
  const waiting = logEl.querySelector('.log-waiting');
  if (waiting && newLogs.length > 0) waiting.remove();

  newLogs.forEach(entry => {
    const div = document.createElement('div');
    div.className = 'log-entry';
    div.style.cssText = 'padding:3px 16px;border-bottom:1px solid rgba(255,255,255,.04);display:flex;gap:8px;align-items:flex-start;';

    const colors = {
      ok:   { text: '#29fd53', bg: 'rgba(41,253,83,.04)' },
      fail: { text: '#FF4D4F', bg: 'rgba(255,77,79,.06)' },
      step: { text: '#60A5FA', bg: '' },
      info: { text: 'var(--muted)', bg: '' },
    };
    const c = colors[entry.type] || colors.info;
    if (c.bg) div.style.background = c.bg;

    div.innerHTML = `
      <span style="color:#555;flex-shrink:0;font-size:10px;margin-top:1px;font-family:monospace">${entry.time}</span>
      <span style="color:${c.text};font-size:11px;line-height:1.5">${entry.text}</span>`;
    logEl.appendChild(div);
  });

  if (extraLine) {
    const div = document.createElement('div');
    div.style.cssText = 'padding:6px 16px;font-size:11px;color:var(--accent);font-weight:700;border-top:1px solid var(--border);margin-top:4px;';
    div.textContent = extraLine;
    logEl.appendChild(div);
  }

  // Auto scroll ke bawah
  logEl.scrollTop = logEl.scrollHeight;
}

function updateDuration() {
  if (!scrapeStartTime) return;
  const secs = Math.floor((Date.now() - scrapeStartTime) / 1000);
  const m = Math.floor(secs / 60);
  const s = secs % 60;
  document.getElementById('pp-duration').textContent = m > 0 ? `${m}m ${s}s` : `${s} detik`;
}

function pollStatus() {
  fetch('{{ route("dashboard.akun.status") }}')
    .then(r => r.json())
    .then(data => {
      const popup = document.getElementById('popup-progress');

      if (data.running) {
        popup.style.display = 'flex';

        // Init start time
        if (!scrapeStartTime) {
          scrapeStartTime = Date.now();
          document.getElementById('pp-start-time').textContent =
            new Date().toLocaleTimeString('id-ID');
          durationInterval = setInterval(updateDuration, 1000);
        }

        // Update progress bar
        if (data.progress?.total) {
          const pct = Math.round(data.progress.current / data.progress.total * 100);
          document.getElementById('pp-bar').style.width   = pct + '%';
          document.getElementById('pp-pct').textContent   = pct + '%';
          document.getElementById('pp-count').textContent =
            `${data.progress.current}/${data.progress.total}`;
          document.getElementById('pp-label').textContent =
            data.progress.label || 'Memproses...';
        }

        // Render log dari server (replace semua)
        if (data.logs && data.logs.length > lastLogCount) {
          lastLogCount = data.logs.length;
          renderLogs(data.logs);
        }

      } else {
        // Selesai
        if (popup.style.display === 'flex') {
          clearInterval(durationInterval);
          document.getElementById('pp-spinner').style.animation = 'none';
          document.getElementById('pp-spinner').textContent = '✓';

          // Render log terakhir
          if (data.logs) renderLogs(data.logs);

          if (data.last_result) {
            const r = data.last_result;
            renderLogs(data.logs, `━━ SELESAI: ${r.berhasil} OK · ${r.gagal} gagal · ${r.total_baru} txn baru ━━`);
          }

          setTimeout(() => window.location.reload(), 4000);
        }
      }
    }).catch(() => {});
}

// Jalankan polling jika sedang running atau cek setiap kali halaman load
@if($isRunning)
scrapeStartTime = Date.now() - 5000; // estimasi sudah berjalan 5 detik
durationInterval = setInterval(updateDuration, 1000);
pollStatus();
@endif
setInterval(pollStatus, 2000); // Polling tiap 2 detik

// ── Modal edit kredensial inline ──────────────────────────────
function bukaEditAkun(id, label, username, isActive) {
  document.getElementById('form-edit-akun').action = `/dashboard/akun/${id}`;
  document.getElementById('ea-label').value    = label    || '';
  document.getElementById('ea-username').value = username || '';
  document.getElementById('ea-password').value = '';
  document.getElementById('ea-password').type  = 'password';
  document.getElementById('ea-active').checked = isActive == 1;
  document.getElementById('ea-pin-info').style.display = 'none';
  document.getElementById('edit-akun-sub').textContent = username || label;
  document.getElementById('modal-edit-akun').style.display = 'flex';
  document.body.style.overflow = 'hidden';

  // Fetch pin info
  if (id) {
    fetch(`/dashboard/akun/${id}/password`, {
      headers: { 'Accept': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(data => {
      const info = document.getElementById('ea-pin-info');
      if (data.success) {
        info.textContent = 'PIN tersimpan — kosongkan jika tidak ingin mengubah · klik Lihat untuk tampilkan';
        info.style.color = 'var(--muted)';
        info.style.display = 'block';
      }
    }).catch(() => {});
  }
}

function closeEditAkun() {
  document.getElementById('modal-edit-akun').style.display = 'none';
  document.body.style.overflow = '';
}

async function toggleEaPassword() {
  const input = document.getElementById('ea-password');
  const info  = document.getElementById('ea-pin-info');
  const form  = document.getElementById('form-edit-akun');
  const id    = form.action.split('/').pop();

  if (input.type === 'text') {
    input.type = 'password';
    return;
  }
  if (input.value) { input.type = 'text'; return; }

  try {
    const res  = await fetch(`/dashboard/akun/${id}/password`, {
      headers: { 'Accept': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    const data = await res.json();
    if (data.success) {
      input.value = data.password;
      input.type  = 'text';
      info.textContent = '⚠ PIN ditampilkan — auto-sembunyikan 8 detik';
      info.style.color = '#F59E0B';
      info.style.display = 'block';
      setTimeout(() => { input.type = 'password'; }, 8000);
    }
  } catch(e) {}
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeEditAkun();
});
</script>
@endpush