@extends('layouts.app')
@section('title', 'Monitor NIK')

@section('content')

{{-- Header + Filter ─────────────────────────────────────────────────── --}}
<div class="mb-5">
  <h1 class="text-lg font-semibold mb-3">Monitor NIK per Transaksi</h1>
  <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">

      {{-- Dropdown pangkalan (alfabet) --}}
      <div class="md:col-span-2">
        <label class="block text-xs text-gray-500 mb-1">Pangkalan</label>
        <select name="pangkalan_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
          <option value="">— Semua Pangkalan —</option>
          @foreach($pangkalans->sortBy('label') as $p)
          <option value="{{ $p['id'] }}" {{ $pangkalanId === $p['id'] ? 'selected' : '' }}>
            {{ $p['label'] }}
          </option>
          @endforeach
        </select>
      </div>

      {{-- Tipe konsumen --}}
      <div>
        <label class="block text-xs text-gray-500 mb-1">Tipe Konsumen</label>
        <select name="kategori"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">— Semua Tipe —</option>
          <option value="Rumah Tangga"    {{ $filterKat==='Rumah Tangga'    ? 'selected':'' }}>Rumah Tangga</option>
          <option value="Usaha Mikro"     {{ $filterKat==='Usaha Mikro'     ? 'selected':'' }}>Usaha Mikro</option>
          <option value="Pengecer"        {{ $filterKat==='Pengecer'        ? 'selected':'' }}>Pengecer</option>
          <option value="Sub Pangkalan"   {{ $filterKat==='Sub Pangkalan'   ? 'selected':'' }}>Sub Pangkalan</option>
        </select>
      </div>

      {{-- Status --}}
      <div>
        <label class="block text-xs text-gray-500 mb-1">Status</label>
        <select name="status"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">— Semua Status —</option>
          <option value="alert" {{ $filterStatus==='alert' ? 'selected':'' }}>🔴 Pelanggaran</option>
          <option value="warn"  {{ $filterStatus==='warn'  ? 'selected':'' }}>🟡 Perlu Pantau</option>
          <option value="aman"  {{ $filterStatus==='aman'  ? 'selected':'' }}>🟢 Aman</option>
          <option value="new"   {{ $filterStatus==='new'   ? 'selected':'' }}>🔵 Data Baru</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Dari</label>
        <input type="date" name="from" value="{{ $from }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Sampai</label>
        <input type="date" name="to" value="{{ $to }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Interval Min (hari)</label>
        <select name="interval" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="5"  {{ $minInterval==5  ? 'selected':'' }}>5 hari</option>
          <option value="7"  {{ $minInterval==7  ? 'selected':'' }}>7 hari</option>
          <option value="14" {{ $minInterval==14 ? 'selected':'' }}>14 hari</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Cari NIK / Nama</label>
        <input type="text" name="search" value="{{ $search }}" placeholder="NIK atau nama..."
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
      </div>
    </div>

    <div class="flex gap-2 mt-3">
      <button type="submit"
              class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-700">
        Filter
      </button>
      <a href="{{ route('dashboard.nik.list') }}"
         class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">Reset</a>
        {{-- Di halaman nik.blade.php --}}
        @include('components.export-buttons', [
            'type'        => 'nik',
            'from'        => $from,
            'to'          => $to,
            'pangkalanId' => $pangkalanId,
        ])
    </div>
  </form>
</div>


{{-- Warning pengecer --}}
@if(!empty($pengecerWarnings))
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
  <p class="font-medium text-sm text-red-800 mb-2">⚠ Pengecer melebihi 10% total pangkalan</p>
  @foreach($pengecerWarnings as $w)
  <p class="text-xs text-red-700">• {{ $w['pesan'] }}</p>
  @endforeach
</div>
@endif

{{-- Tabel NIK ────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">NIK (sensor)</th>
        <th class="text-left px-4 py-3">Nama</th>
        <th class="text-left px-4 py-3">Tipe</th>
        <th class="text-right px-4 py-3">Txn</th>
        <th class="text-right px-4 py-3">Tabung</th>
        <th class="text-right px-4 py-3">Aset</th>
        <th class="text-right px-4 py-3">Jarak Rata²</th>
        <th class="text-left px-4 py-3">Pelanggaran</th>
        <th class="text-left px-4 py-3">Status</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($groups as $n)
      @php
        $hasAlert = collect($n['all_alerts'])->where('level','alert')->isNotEmpty();
        $hasWarn  = collect($n['all_alerts'])->where('level','warn')->isNotEmpty();
        $rowBg    = $hasAlert ? 'bg-red-50/40' : ($hasWarn ? 'bg-amber-50/30' : 'hover:bg-gray-50');
      @endphp
      <tr class="{{ $rowBg }}">
        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $n['nik'] }}</td>
        <td class="px-4 py-3 font-medium">
          {{ $n['nama'] }}
          @if($n['store_name'])
            <span class="block text-xs text-gray-400 font-normal">{{ $n['store_name'] }}</span>
          @endif
        </td>
        <td class="px-4 py-3">
          @php
            $kat = $n['kategori'] ?? 'Rumah Tangga';
            $katColor = match(true) {
              str_contains(strtolower($kat),'rumah') => 'bg-blue-100 text-blue-700',
              str_contains(strtolower($kat),'mikro') => 'bg-purple-100 text-purple-700',
              str_contains(strtolower($kat),'pengecer') => 'bg-orange-100 text-orange-700',
              str_contains(strtolower($kat),'sub') => 'bg-orange-100 text-orange-700',
              default => 'bg-gray-100 text-gray-600',
            };
          @endphp
          <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $katColor }}">
            {{ $kat }}
          </span>
        </td>
        <td class="px-4 py-3 text-right">{{ $n['total_txn'] }}</td>
        <td class="px-4 py-3 text-right font-semibold">{{ $n['total_tabung'] }}</td>
        <td class="px-4 py-3 text-right text-xs">
          <span title="Aset kepemilikan tabung (max sekali beli)">
            {{ $n['max_tabung_sekali'] }} tb
          </span>
        </td>
        <td class="px-4 py-3 text-right text-xs {{ ($n['avg_gap'] !== null && $n['avg_gap'] < $minInterval) ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
          {{ $n['avg_gap'] !== null ? $n['avg_gap'].'h' : '—' }}
        </td>
        <td class="px-4 py-3">
          @if(!empty($n['all_alerts']))
            @foreach(array_slice($n['all_alerts'], 0, 2) as $v)
            <div class="text-xs {{ $v['level']==='alert' ? 'text-red-600' : 'text-amber-600' }} leading-tight">
              {{ $v['level']==='alert' ? '🔴' : '🟡' }} {{ Str::limit($v['pesan'], 45) }}
            </div>
            @endforeach
            @if(count($n['all_alerts']) > 2)
            <div class="text-xs text-gray-400">+{{ count($n['all_alerts'])-2 }} lainnya</div>
            @endif
          @else
            <span class="text-xs text-gray-300">—</span>
          @endif
        </td>
        <td class="px-4 py-3">
          @switch($n['status'])
            @case('alert') <span class="badge-alert">🔴 Pelanggaran</span> @break
            @case('warn')  <span class="badge-warn">🟡 Perlu Pantau</span> @break
            @case('new')   <span class="badge-new">🔵 Data Baru</span>    @break
            @default       <span class="badge-aman">🟢 Aman</span>
          @endswitch
        </td>
        <td class="px-4 py-3 text-right">
          <button onclick="showNikPopup({{ Js::from($n) }})"
                  class="text-xs text-blue-600 hover:underline">Detail</button>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="10" class="px-4 py-10 text-center text-gray-400">
          Tidak ada data. Ubah filter atau jalankan scraping terlebih dahulu.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="text-xs text-gray-400 mt-2">
  {{ $groups->count() }} individu ·
  {{ $from }} s/d {{ $to }}
  @if($pangkalanId) · Pangkalan: {{ $pangkalans->firstWhere('id', $pangkalanId)['label'] ?? $pangkalanId }} @endif
</p>

{{-- Popup Detail NIK --}}
<div id="nikPopupOverlay"
     class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50"
     onclick="closeNikPopup()">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-screen overflow-y-auto"
       onclick="event.stopPropagation()">

    {{-- Header popup --}}
    <div class="flex items-start justify-between p-5 border-b border-gray-100">
      <div>
        <p class="text-xs text-gray-400 font-mono mb-0.5" id="popNik"></p>
        <h3 class="font-semibold text-base" id="popNama"></h3>
        <div class="flex items-center gap-2 mt-1.5 flex-wrap" id="popBadges"></div>
      </div>
      <button onclick="closeNikPopup()" class="text-gray-400 hover:text-gray-700 text-xl leading-none ml-4">×</button>
    </div>

    {{-- Metrik ringkasan --}}
    <div class="grid grid-cols-5 gap-0 border-b border-gray-100" id="popMetrik"></div>

    {{-- Pelanggaran (jika ada) --}}
    <div id="popAlerts" class="p-4 border-b border-gray-100 hidden"></div>

    {{-- Tabel riwayat transaksi --}}
    <div class="p-4">
      <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Riwayat Transaksi</p>
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-400 uppercase">
          <tr>
            <th class="text-left pb-2">#</th>
            <th class="text-left pb-2">Tanggal</th>
            <th class="text-left pb-2">Waktu</th>
            <th class="text-right pb-2">Tabung</th>
            <th class="text-right pb-2">Jarak</th>
            <th class="text-left pb-2">Status</th>
          </tr>
        </thead>
        <tbody id="popTxnBody" class="divide-y divide-gray-50"></tbody>
      </table>
    </div>

    <div class="p-4 pt-0 flex justify-end">
      <button onclick="closeNikPopup()"
              class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">Tutup</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
const minInterval = {{ $minInterval }};

function showNikPopup(n) {
  const txns = n.txns || [];

  // NIK & nama
  document.getElementById('popNik').textContent   = n.nik;
  document.getElementById('popNama').textContent  = n.nama;

  // Badges
  const katColor = {
    'rumah tangga': 'bg-blue-100 text-blue-700',
    'usaha mikro':  'bg-purple-100 text-purple-700',
    'pengecer':     'bg-orange-100 text-orange-700',
    'sub pangkalan':'bg-orange-100 text-orange-700',
  };
  const kat      = (n.kategori || 'Rumah Tangga').toLowerCase();
  const katCls   = Object.entries(katColor).find(([k]) => kat.includes(k))?.[1] ?? 'bg-gray-100 text-gray-600';
  const statusMap = { alert:'🔴 Pelanggaran', warn:'🟡 Perlu Pantau', aman:'🟢 Aman', new:'🔵 Data Baru' };
  const statusCls = { alert:'badge-alert', warn:'badge-warn', aman:'badge-aman', new:'badge-new' };

  document.getElementById('popBadges').innerHTML = `
    <span class="text-xs px-2 py-0.5 rounded-full font-medium ${katCls}">${n.kategori}</span>
    <span class="${statusCls[n.status] ?? 'badge-new'}">${statusMap[n.status] ?? n.status}</span>
    ${n.store_name ? `<span class="text-xs text-gray-400">📍 ${n.store_name}</span>` : ''}
  `;

  // Metrik
  document.getElementById('popMetrik').innerHTML = `
    <div class="text-center py-3 border-r border-gray-100">
      <p class="text-xl font-semibold">${n.total_txn}</p>
      <p class="text-xs text-gray-400">Transaksi</p>
    </div>
    <div class="text-center py-3 border-r border-gray-100">
      <p class="text-xl font-semibold">${n.total_tabung}</p>
      <p class="text-xs text-gray-400">Total Tabung</p>
    </div>
    <div class="text-center py-3 border-r border-gray-100">
      <p class="text-xl font-semibold ${n.avg_gap !== null && n.avg_gap < minInterval ? 'text-red-600' : ''}">${n.avg_gap ?? '—'}</p>
      <p class="text-xs text-gray-400">Jarak Rata² (h)</p>
    </div>
    <div class="text-center py-3 border-r border-gray-100">
      <p class="text-xl font-semibold text-blue-600">${n.max_tabung_sekali}</p>
      <p class="text-xs text-gray-400">Aset Tabung</p>
    </div>
    <div class="text-center py-3">
      <p class="text-xl font-semibold text-red-500">${(n.all_alerts||[]).length}</p>
      <p class="text-xs text-gray-400">Pelanggaran</p>
    </div>
  `;

  // Pelanggaran
  const alertsDiv = document.getElementById('popAlerts');
  const alerts    = n.all_alerts || [];
  if (alerts.length > 0) {
    alertsDiv.classList.remove('hidden');
    alertsDiv.innerHTML = `<p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Pelanggaran & Warning</p>` +
      alerts.map(a => `
        <div class="flex items-start gap-2 p-2.5 rounded-lg mb-1.5 ${a.level==='alert' ? 'bg-red-50 border border-red-200' : 'bg-amber-50 border border-amber-200'}">
          <span>${a.level==='alert' ? '🔴' : '🟡'}</span>
          <div>
            <p class="text-xs font-medium ${a.level==='alert' ? 'text-red-800' : 'text-amber-800'}">${a.pesan}</p>
            ${a.tanggal ? `<p class="text-xs ${a.level==='alert' ? 'text-red-500' : 'text-amber-500'} mt-0.5">${a.tanggal}</p>` : ''}
          </div>
        </div>`).join('');
  } else {
    alertsDiv.classList.add('hidden');
  }

  // Tabel transaksi
  const sorted = [...txns].sort((a,b) => new Date(a.transaction_at) - new Date(b.transaction_at));
  const rows = sorted.map((t, i) => {
    const jam    = new Date(t.transaction_at).toLocaleString('id', {timeZone:'Asia/Jakarta', hour:'2-digit', minute:'2-digit'});
    const tgl    = new Date(t.transaction_at).toLocaleDateString('id', {timeZone:'Asia/Jakarta', day:'2-digit', month:'2-digit', year:'numeric'});
    const jamStr = new Date(t.transaction_at).toLocaleTimeString('id', {timeZone:'Asia/Jakarta', hour:'2-digit', minute:'2-digit'});
    const jamAbn = jamStr < '05:30' || jamStr > '20:00';
    const prev   = i > 0 ? sorted[i-1] : null;
    const gap    = prev ? Math.round(Math.abs(new Date(t.transaction_at) - new Date(prev.transaction_at)) / 86400000) : null;
    const gapErr = gap !== null && gap < minInterval;

    // Cari alert relevan
    const tglKey = new Date(t.transaction_at).toLocaleDateString('id', {timeZone:'Asia/Jakarta', day:'2-digit', month:'2-digit'}).replace('/','/');
    const txAlert = alerts.find(a => a.tanggal && a.tanggal.includes(tglKey));
    const hasViol = txAlert?.level === 'alert';
    const hasWarn = txAlert?.level === 'warn' || jamAbn;
    const rowBg   = hasViol ? 'bg-red-50' : hasWarn ? 'bg-amber-50/50' : '';

    let statusHtml = '<span class="text-gray-300 text-xs">—</span>';
    if (hasViol) statusHtml = '<span class="badge-alert text-xs">Alert</span>';
    else if (hasWarn) statusHtml = '<span class="badge-warn text-xs">Warn</span>';

    return `<tr class="${rowBg}">
      <td class="py-2 text-gray-400">${i+1}</td>
      <td class="py-2 font-medium">${tgl}</td>
      <td class="py-2 ${jamAbn ? 'text-amber-600 font-medium' : 'text-gray-500'}">${jam}${jamAbn ? ' ⚠' : ''}</td>
      <td class="py-2 text-right font-semibold">${t.total}</td>
      <td class="py-2 text-right ${gapErr ? 'text-red-600 font-semibold' : 'text-gray-500'}">${gap !== null ? gap+' h' : '—'}</td>
      <td class="py-2">${statusHtml}</td>
    </tr>`;
  }).join('');

  document.getElementById('popTxnBody').innerHTML = rows || '<tr><td colspan="6" class="py-4 text-center text-gray-400 text-xs">Tidak ada data transaksi</td></tr>';

  document.getElementById('nikPopupOverlay').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeNikPopup() {
  document.getElementById('nikPopupOverlay').classList.add('hidden');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeNikPopup(); });
</script>
@endpush