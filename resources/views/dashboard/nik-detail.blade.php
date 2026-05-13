@extends('layouts.app')
@section('title', 'Detail NIK')

@section('content')

@php
  $status      = $analysis['status'];
  $kategori    = $analysis['kategori'] ?? 'Rumah Tangga';
  $allAlerts   = $analysis['all_alerts'] ?? [];
  $avgGap      = $analysis['avg_gap'];
  $totalTabung = $analysis['total_tabung'];
  $maxTabung   = $analysis['max_tabung_sekali'];
  $statusLabel = ['alert'=>'Pelanggaran','warn'=>'Perlu Pantau','aman'=>'Aman','new'=>'Data Baru'];
  $statusClass = ['alert'=>'badge-alert','warn'=>'badge-warn','aman'=>'badge-aman','new'=>'badge-new'];
  $statusEmoji = ['alert'=>'🔴','warn'=>'🟡','aman'=>'🟢','new'=>'🔵'];
@endphp

<div class="mb-4 flex items-center gap-3">
  <a href="{{ route('dashboard.nik.list', ['from'=>$from,'to'=>$to,'interval'=>$minInterval]) }}"
     class="text-xs text-gray-400 hover:text-gray-700">← Kembali</a>
  <span class="text-gray-300">|</span>
  <span class="text-xs text-gray-400">{{ $from }} s/d {{ $to }}</span>
</div>

{{-- Header NIK --}}
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
  <div class="flex items-start justify-between flex-wrap gap-4">
    <div>
      <p class="text-xs text-gray-400 font-mono mb-1">{{ $nik }}</p>
      <h1 class="text-xl font-semibold">{{ $nama }}</h1>
      <div class="flex items-center gap-2 mt-1.5 flex-wrap">
        @php
          $katColor = 'bg-gray-100 text-gray-600';
          $katLower = strtolower($kategori);
          if (str_contains($katLower,'rumah'))    $katColor = 'bg-blue-100 text-blue-700';
          elseif (str_contains($katLower,'mikro')) $katColor = 'bg-purple-100 text-purple-700';
          elseif (str_contains($katLower,'pengecer') || str_contains($katLower,'sub')) $katColor = 'bg-orange-100 text-orange-700';
        @endphp
        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $katColor }}">{{ $kategori }}</span>
        <span class="{{ $statusClass[$status] ?? 'badge-new' }}">
          {{ $statusEmoji[$status] ?? '' }} {{ $statusLabel[$status] ?? $status }}
        </span>
        @if($analysis['store_name'])
          <span class="text-xs text-gray-400">📍 {{ $analysis['store_name'] }}</span>
        @endif
      </div>
    </div>
    <div class="flex gap-4 flex-wrap">
      <div class="text-center">
        <p class="text-2xl font-semibold">{{ $txns->count() }}</p>
        <p class="text-xs text-gray-400">Transaksi</p>
      </div>
      <div class="text-center">
        <p class="text-2xl font-semibold">{{ $totalTabung }}</p>
        <p class="text-xs text-gray-400">Total Tabung</p>
      </div>
      <div class="text-center">
        <p class="text-2xl font-semibold {{ $avgGap !== null && $avgGap < $minInterval ? 'text-red-600':'' }}">
          {{ $avgGap ?? '—' }}
        </p>
        <p class="text-xs text-gray-400">Jarak Rata² (h)</p>
      </div>
      <div class="text-center">
        <p class="text-2xl font-semibold text-blue-600">{{ $maxTabung }}</p>
        <p class="text-xs text-gray-400">Aset Tabung</p>
      </div>
      <div class="text-center">
        <p class="text-2xl font-semibold text-red-500">{{ count($allAlerts) }}</p>
        <p class="text-xs text-gray-400">Pelanggaran</p>
      </div>
    </div>
  </div>
</div>

{{-- Daftar Pelanggaran --}}
@if(!empty($allAlerts))
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
  <h2 class="font-medium text-sm mb-3">Detail Pelanggaran & Warning</h2>
  <div class="space-y-2">
    @foreach($allAlerts as $v)
    <div class="flex items-start gap-3 p-3 rounded-lg {{ $v['level']==='alert' ? 'bg-red-50 border border-red-200' : 'bg-amber-50 border border-amber-200' }}">
      <span class="text-base mt-0.5">{{ $v['level']==='alert' ? '🔴' : '🟡' }}</span>
      <div class="flex-1">
        <p class="text-xs font-medium {{ $v['level']==='alert' ? 'text-red-800' : 'text-amber-800' }}">
          {{ $v['pesan'] }}
        </p>
        @if(isset($v['tanggal']))
        <p class="text-xs {{ $v['level']==='alert' ? 'text-red-500' : 'text-amber-500' }} mt-0.5">
          {{ $v['tanggal'] }}
        </p>
        @endif
      </div>
      <span class="text-xs {{ $v['level']==='alert' ? 'text-red-500' : 'text-amber-500' }} whitespace-nowrap">
        {{ strtoupper($v['level']) }}
      </span>
    </div>
    @endforeach
  </div>
</div>
@endif

{{-- Grafik --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
  <h2 class="font-medium text-sm mb-3">Riwayat Pembelian</h2>
  <div style="position:relative;height:180px">
    <canvas id="chartNik"></canvas>
  </div>
</div>

{{-- Tabel transaksi --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-100">
    <h2 class="font-medium text-sm">Riwayat Transaksi Lengkap
      <span class="text-xs text-gray-400 font-normal ml-2">Klik baris untuk detail popup</span>
    </h2>
  </div>
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-2">#</th>
        <th class="text-left px-4 py-2">Tanggal</th>
        <th class="text-left px-4 py-2">Waktu (WIB)</th>
        <th class="text-right px-4 py-2">Tabung</th>
        <th class="text-right px-4 py-2">Jarak</th>
        <th class="text-left px-4 py-2">Status</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @foreach($txns->sortBy('transaction_at')->values() as $idx => $t)
      @php
        $txAt     = \Carbon\Carbon::parse($t->transaction_at)->setTimezone('Asia/Jakarta');
        $jamStr   = $txAt->format('H:i');
        $jamAbn   = $jamStr < '05:30' || $jamStr > '20:00';
        $prevTx   = $idx > 0 ? $txns->sortBy('transaction_at')->values()[$idx-1] : null;
        $gap      = $prevTx ? \Carbon\Carbon::parse($prevTx->transaction_at)->diffInDays(\Carbon\Carbon::parse($t->transaction_at)) : null;
        $tglKey   = $txAt->format('d/m');
        $txAlerts = collect($allAlerts)->filter(fn($v) => isset($v['tanggal']) && str_contains($v['tanggal'], $tglKey));
        $hasViol  = $txAlerts->where('level','alert')->isNotEmpty();
        $hasWarn  = $txAlerts->where('level','warn')->isNotEmpty() || $jamAbn;
        $rowBg    = $hasViol ? 'bg-red-50' : ($hasWarn ? 'bg-amber-50/50' : 'hover:bg-gray-50');
      @endphp
      <tr class="{{ $rowBg }} cursor-pointer" onclick="showDetail({{ $idx }})">
        <td class="px-4 py-2.5 text-gray-400">{{ $idx + 1 }}</td>
        <td class="px-4 py-2.5 font-medium">{{ $txAt->format('d/m/Y') }}</td>
        <td class="px-4 py-2.5 {{ $jamAbn ? 'text-amber-600 font-medium' : 'text-gray-500' }}">
          {{ $jamStr }}@if($jamAbn) ⚠@endif
        </td>
        <td class="px-4 py-2.5 text-right font-semibold">{{ $t->total }}</td>
        <td class="px-4 py-2.5 text-right {{ $gap !== null && $gap < $minInterval ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
          {{ $gap !== null ? $gap.' h' : '—' }}
        </td>
        <td class="px-4 py-2.5">
          @if($hasViol)
            <span class="badge-alert text-xs">Alert</span>
          @elseif($hasWarn)
            <span class="badge-warn text-xs">Warn</span>
          @else
            <span class="text-gray-300 text-xs">—</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Popup --}}
<div id="popupOverlay" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50"
     onclick="closePopup()">
  <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-base">Detail Transaksi</h3>
      <button onclick="closePopup()" class="text-gray-400 hover:text-gray-700 text-xl leading-none">×</button>
    </div>
    <div id="popupContent"></div>
    <div class="mt-4 pt-4 border-t border-gray-100 flex justify-end">
      <button onclick="closePopup()" class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">
        Tutup
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
const minInt  = {{ $minInterval }};
const katStr  = {{ Js::from($kategori) }};
const allAlerts = {{ Js::from($allAlerts) }};

const txnData = {{ Js::from($txns->sortBy('transaction_at')->values()->map(function($t) {
  $jam = \Carbon\Carbon::parse($t->transaction_at)->setTimezone('Asia/Jakarta');
  return [
    'tanggal'     => $jam->format('d/m/Y'),
    'tgl_short'   => $jam->format('d/m'),
    'waktu'       => $jam->format('H:i:s'),
    'tabung'      => $t->total,
    'kategori'    => $t->category ?? 'Rumah Tangga',
    'customer_id' => $t->customer_report_id ?? '',
    'pangkalan'   => $t->store_name ?? '—',
  ];
})->values()) }};

// Grafik
new Chart(document.getElementById('chartNik'), {
  type: 'bar',
  data: {
    labels: txnData.map(t => t.tgl_short),
    datasets: [{
      label: 'Tabung',
      data:  txnData.map(t => t.tabung),
      backgroundColor: txnData.map(t => {
        const hasAlert = allAlerts.some(a => a.level === 'alert' && a.tanggal && a.tanggal.includes(t.tgl_short));
        const hasWarn  = allAlerts.some(a => a.level === 'warn'  && a.tanggal && a.tanggal.includes(t.tgl_short));
        const jam = t.waktu.substring(0,5);
        const jamAbn = jam < '05:30' || jam > '20:00';
        return hasAlert ? '#EF4444' : (hasWarn || jamAbn) ? '#F59E0B' : '#3B82F6';
      }),
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 } },
      x: { ticks: { autoSkip: false, maxRotation: 45 } }
    }
  }
});

function showDetail(idx) {
  const t     = txnData[idx];
  const prevT = idx > 0 ? txnData[idx-1] : null;
  const gap   = prevT ? daysBetween(prevT.tanggal, t.tanggal) : null;
  const jam   = t.waktu.substring(0,5);
  const jamAbn = jam < '05:30' || jam > '20:00';
  const tglKey = t.tgl_short;
  const txAlerts = allAlerts.filter(a => a.tanggal && a.tanggal.includes(tglKey));

  let html = `<div class="space-y-3">
    <div class="grid grid-cols-2 gap-3">
      <div class="bg-gray-50 rounded-lg p-3">
        <p class="text-xs text-gray-400">Tanggal</p>
        <p class="font-medium text-sm mt-0.5">${t.tanggal}</p>
      </div>
      <div class="bg-gray-50 rounded-lg p-3">
        <p class="text-xs text-gray-400">Waktu (WIB)</p>
        <p class="font-medium text-sm mt-0.5 ${jamAbn ? 'text-amber-600':''}">${t.waktu}${jamAbn?' ⚠':''}</p>
      </div>
      <div class="bg-gray-50 rounded-lg p-3">
        <p class="text-xs text-gray-400">Jumlah Tabung</p>
        <p class="font-semibold text-2xl mt-0.5">${t.tabung}</p>
      </div>
      <div class="bg-gray-50 rounded-lg p-3">
        <p class="text-xs text-gray-400">Jarak Sebelumnya</p>
        <p class="font-medium text-sm mt-0.5 ${gap !== null && gap < minInt ? 'text-red-600 font-bold':''}">
          ${gap !== null ? gap+' hari' : '— (pertama)'}
        </p>
      </div>
    </div>
    <div class="bg-gray-50 rounded-lg p-3">
      <p class="text-xs text-gray-400 mb-1">Info</p>
      <p class="text-xs text-gray-600">Tipe: <strong>${t.kategori}</strong></p>
      <p class="text-xs text-gray-600 mt-0.5">Pangkalan: <strong>${t.pangkalan}</strong></p>
    </div>`;

  if (jamAbn) {
    html += `<div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
      <p class="text-xs font-medium text-amber-800">⚠ Di luar jam normal (05:30 - 20:00 WIB)</p>
      <p class="text-xs text-amber-600 mt-0.5">Jam transaksi: ${jam} WIB</p>
    </div>`;
  }

  txAlerts.forEach(a => {
    const isAlert = a.level === 'alert';
    html += `<div class="${isAlert ? 'bg-red-50 border-red-200':'bg-amber-50 border-amber-200'} border rounded-lg p-3">
      <p class="text-xs font-medium ${isAlert ? 'text-red-800':'text-amber-800'}">${isAlert ? '🔴':'🟡'} ${a.pesan}</p>
    </div>`;
  });

  if (!jamAbn && txAlerts.length === 0 && (gap === null || gap >= minInt)) {
    html += `<div class="bg-green-50 border border-green-200 rounded-lg p-3">
      <p class="text-xs text-green-700">🟢 Transaksi ini sesuai aturan</p>
    </div>`;
  }

  html += `</div>`;
  document.getElementById('popupContent').innerHTML = html;
  document.getElementById('popupOverlay').classList.remove('hidden');
}

function closePopup() {
  document.getElementById('popupOverlay').classList.add('hidden');
}

function daysBetween(d1, d2) {
  const p1 = d1.split('/'), p2 = d2.split('/');
  const date1 = new Date(p1[2]+'-'+p1[1]+'-'+p1[0]);
  const date2 = new Date(p2[2]+'-'+p2[1]+'-'+p2[0]);
  return Math.round(Math.abs(date2-date1)/(1000*60*60*24));
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopup(); });
</script>
@endpush