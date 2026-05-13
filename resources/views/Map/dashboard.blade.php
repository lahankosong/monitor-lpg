@extends('layouts.app')
@section('title', 'Dashboard MAP')

@section('content')

{{-- ── Header + Filter ─────────────────────────────────── --}}
<div class="flex items-center justify-between flex-wrap gap-3 mb-6">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Dashboard MAP</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      MyPertamina Analytics Platform — monitoring penyaluran & transaksi LPG subsidi
    </p>
  </div>
  <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <input type="date" name="from" value="{{ $from }}"
           style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:6px 12px;font-size:13px">
    <span style="color:var(--muted);font-size:12px">s/d</span>
    <input type="date" name="to" value="{{ $to }}"
           style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:6px 12px;font-size:13px">
    <button type="submit"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:13px;cursor:pointer;font-weight:500">
      Filter
    </button>
  </form>
</div>

{{-- ── Summary Cards ───────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px">
  @php
    $cards = [
      ['label'=>'Total Pangkalan',    'value'=>$totalPangkalan,      'sub'=>$pangkalanAktif.' aktif',          'color'=>'#3B82F6','icon'=>'🏪'],
      ['label'=>'Total Tabung Keluar','value'=>number_format($totalTransaksiTabung), 'sub'=>'dari transaksi', 'color'=>'#10B981','icon'=>'🔵'],
      ['label'=>'Penyaluran Resmi',   'value'=>number_format($totalStokDisalurkan),  'sub'=>'stock redeem',   'color'=>'#8B5CF6','icon'=>'📦'],
      ['label'=>'Total Transaksi',    'value'=>number_format($totalTransaksiCount),   'sub'=>'periode ini',   'color'=>'#F59E0B','icon'=>'📋'],
      ['label'=>'Total NIK',          'value'=>number_format($totalNik),              'sub'=>'konsumen unik', 'color'=>'#6366F1','icon'=>'👤'],
      ['label'=>'Ada Pelanggaran',    'value'=>$pangkalanViolation,  'sub'=>'dari '.$totalPangkalan.' pangkalan','color'=>'#EF4444','icon'=>'⚠️'],
    ];
  @endphp
  @foreach($cards as $c)
  <div class="stat-card" style="border-left:3px solid {{ $c['color'] }}">
    <div style="display:flex;align-items:flex-start;justify-content:space-between">
      <div>
        <p style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600">{{ $c['label'] }}</p>
        <p style="font-size:24px;font-weight:700;color:var(--text);margin-top:4px;line-height:1">{{ $c['value'] }}</p>
        <p style="font-size:11px;color:var(--muted);margin-top:3px">{{ $c['sub'] }}</p>
      </div>
      <span style="font-size:24px;opacity:.7">{{ $c['icon'] }}</span>
    </div>
  </div>
  @endforeach
</div>

{{-- ── Grafik + Perbandingan ───────────────────────────── --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px">
  

  {{-- Grafik 7 hari --}}
  <div class="card" style="padding:20px">
    <h2 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:16px">
      Transaksi 7 Hari Terakhir
    </h2>
    <div style="height:200px">
      <canvas id="chartDays"></canvas>
    </div>
  </div>

  {{-- Penyaluran vs Transaksi --}}
  <div class="card" style="padding:20px">
    <h2 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:16px">
      Penyaluran vs Transaksi
    </h2>
    <div style="height:200px;display:flex;align-items:center;justify-content:center">
      <canvas id="chartCompare"></canvas>
    </div>
    <div style="display:flex;gap:16px;justify-content:center;margin-top:12px">
      <div style="text-align:center">
        <p style="font-size:18px;font-weight:700;color:#8B5CF6">{{ number_format($totalStokDisalurkan) }}</p>
        <p style="font-size:11px;color:var(--muted)">Disalurkan</p>
      </div>
      <div style="width:1px;background:var(--border)"></div>
      <div style="text-align:center">
        <p style="font-size:18px;font-weight:700;color:#10B981">{{ number_format($totalTransaksiTabung) }}</p>
        <p style="font-size:11px;color:var(--muted)">Ditransaksikan</p>
      </div>
    </div>
  </div>
</div>

{{-- ── Tabel Pangkalan ─────────────────────────────────── --}}
<div class="card" style="overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">
      Semua Pangkalan
      <span style="font-size:12px;color:var(--muted);font-weight:400;margin-left:6px">{{ $pangkalans->count() }} pangkalan</span>
    </h2>
    <div style="display:flex;gap:8px">
      <input type="text" id="searchPangkalan" placeholder="Cari pangkalan..."
             oninput="filterTable()"
             style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:5px 12px;font-size:12px;width:180px">
    </div>
  </div>

  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px" id="tblPangkalan">
      <thead>
        <tr style="background:var(--bg)">
          <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">Nama Pangkalan</th>
          <th style="text-align:center;padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">NIK</th>
          <th style="text-align:right;padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Txn</th>
          <th style="text-align:right;padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Tabung</th>
          <th style="text-align:right;padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Stok Sisa</th>
          <th style="text-align:center;padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Pelanggaran</th>
          <th style="text-align:center;padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">Status</th>
          <th style="padding:10px 12px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($pangkalans as $p)
        @php
          $hasViol = $p['has_violation'];
          $rowBg   = $hasViol ? 'rgba(239,68,68,.04)' : 'transparent';
        @endphp
        <tr style="border-top:1px solid var(--border);background:{{ $rowBg }}"
            class="tbl-row hover-row" data-name="{{ strtolower($p['label']) }}">
          <td style="padding:12px 16px">
            <div style="font-weight:600;color:var(--text)">{{ $p['label'] }}</div>
            @if($p['registration_id'])
              <div style="font-size:11px;color:var(--muted);font-family:monospace">{{ $p['registration_id'] }}</div>
            @endif
          </td>
          <td style="padding:12px;text-align:center;font-weight:600;color:var(--text)">
            {{ $p['total_nik'] ?: '—' }}
          </td>
          <td style="padding:12px;text-align:right;color:var(--text)">{{ $p['total_txn'] ?: '—' }}</td>
          <td style="padding:12px;text-align:right;font-weight:700;color:{{ $p['total_tabung'] > 0 ? '#10B981' : 'var(--muted)' }}">
            {{ $p['total_tabung'] ?: '—' }}
          </td>
          <td style="padding:12px;text-align:right;color:{{ $p['stok_available'] > 0 ? 'var(--text)' : 'var(--muted)' }}">
            {{ $p['stok_available'] > 0 ? $p['stok_available'] : '—' }}
          </td>
          <td style="padding:12px;text-align:center">
            @if($hasViol)
              <span style="background:#FEE2E2;color:#991B1B;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:500">
                ⚠ {{ array_sum($p['violations']) }}
              </span>
            @else
              <span style="color:var(--muted);font-size:12px">—</span>
            @endif
          </td>
          <td style="padding:12px;text-align:center">
            @if($p['is_active'])
              <span style="background:#D1FAE5;color:#065F46;font-size:11px;padding:2px 8px;border-radius:99px">Aktif</span>
            @else
              <span style="background:var(--bg);color:var(--muted);font-size:11px;padding:2px 8px;border-radius:99px">Nonaktif</span>
            @endif
          </td>
          <td style="padding:12px;text-align:right">
            <button onclick="showPangkalanPopup({{ Js::from($p) }})"
                    style="background:var(--accent);color:#fff;border:none;border-radius:6px;padding:4px 12px;font-size:12px;cursor:pointer;font-weight:500">
              Lihat
            </button>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" style="padding:40px;text-align:center;color:var(--muted)">
            Belum ada data. Jalankan scraping terlebih dahulu.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ── Popup Detail Pangkalan ──────────────────────────── --}}
<div id="pangkalanPopupOverlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="closePangkalanPopup()">
  <div style="background:var(--surface);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.2);width:100%;max-width:580px;max-height:85vh;overflow-y:auto"
       onclick="event.stopPropagation()">

    {{-- Header popup --}}
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;position:sticky;top:0;background:var(--surface);z-index:1">
      <div>
        <p style="font-size:11px;color:var(--muted);font-family:monospace" id="ppRegId"></p>
        <h3 style="font-size:17px;font-weight:700;color:var(--text);margin-top:2px" id="ppLabel"></h3>
      </div>
      <button onclick="closePangkalanPopup()"
              style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer;line-height:1;padding:0 4px">×</button>
    </div>

    {{-- Metrik utama --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid var(--border)" id="ppMetrik"></div>

    {{-- Stok info --}}
    <div id="ppStok" style="padding:16px 24px;border-bottom:1px solid var(--border);background:var(--bg)"></div>

    {{-- Per tipe konsumen --}}
    <div style="padding:16px 24px;border-bottom:1px solid var(--border)">
      <p style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Per Tipe Konsumen</p>
      <div id="ppTipe" style="display:flex;flex-direction:column;gap:6px"></div>
    </div>

    {{-- Pelanggaran --}}
    <div id="ppViolations" style="padding:16px 24px;border-bottom:1px solid var(--border)"></div>

    <div style="padding:16px 24px;display:flex;justify-content:flex-end;gap:8px">
      <a id="ppNikLink" href="#"
         style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:500;text-decoration:none">
        Monitor NIK →
      </a>
      <button onclick="closePangkalanPopup()"
              style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 18px;font-size:13px;cursor:pointer">
        Tutup
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ── Data untuk grafik ──────────────────────────────────
const chartData = @json($chartDays);

// Grafik 7 hari
new Chart(document.getElementById('chartDays'), {
  type: 'bar',
  data: {
    labels:   chartData.map(d => d.label),
    datasets: [{
      label:           'Tabung',
      data:            chartData.map(d => d.tabung),
      backgroundColor: 'rgba(59,130,246,.7)',
      borderRadius:    4,
    },{
      label:     'Transaksi',
      data:      chartData.map(d => d.txn),
      type:      'line',
      borderColor:     '#10B981',
      backgroundColor: 'rgba(16,185,129,.1)',
      borderWidth:     2,
      pointRadius:     4,
      tension:         .3,
      yAxisID:         'y2',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
    scales: {
      y:  { beginAtZero: true, ticks: { font: { size: 11 } } },
      y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 11 } } }
    }
  }
});

// Grafik donut penyaluran vs transaksi
const total = {{ $totalStokDisalurkan + $totalTransaksiTabung }};
new Chart(document.getElementById('chartCompare'), {
  type: 'doughnut',
  data: {
    labels:   ['Disalurkan', 'Ditransaksikan'],
    datasets: [{
      data:            [{{ $totalStokDisalurkan }}, {{ $totalTransaksiTabung }}],
      backgroundColor: ['#8B5CF6','#10B981'],
      borderWidth:     0,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed.toLocaleString('id')} tabung` } }
    }
  }
});

// ── Filter tabel ───────────────────────────────────────
function filterTable() {
  const q = document.getElementById('searchPangkalan').value.toLowerCase();
  document.querySelectorAll('#tblPangkalan .tbl-row').forEach(row => {
    row.style.display = row.dataset.name.includes(q) ? '' : 'none';
  });
}

// ── Hover effect tabel ─────────────────────────────────
document.querySelectorAll('.hover-row').forEach(r => {
  r.addEventListener('mouseenter', () => r.style.background = 'rgba(59,130,246,.04)');
  r.addEventListener('mouseleave', () => r.style.background = r.dataset.viol ? 'rgba(239,68,68,.04)' : 'transparent');
});

// ── Popup detail pangkalan ─────────────────────────────
const violationLabels = {
  rt_lebih_1_tabung:      'RT beli >1 tabung/transaksi',
  rt_interval_pendek:     'RT interval <5 hari',
  rt_interval_dekat:      'RT interval <7 hari',
  um_terlalu_cepat:       'Usaha Mikro terlalu cepat',
  um_melebihi_kuota:      'Usaha Mikro melebihi kuota',
  jam_abnormal:           'Transaksi di luar jam normal',
  masif_luar_jam:         'Transaksi masif di luar jam',
  pengecer_melebihi_10persen: 'Pengecer >10% total',
};

function showPangkalanPopup(p) {
  document.getElementById('ppLabel').textContent   = p.label;
  document.getElementById('ppRegId').textContent   = p.registration_id || '';
  document.getElementById('ppNikLink').href        = `/dashboard/nik?pangkalan_id=${p.id}&from={{ $from }}&to={{ $to }}`;

  // Metrik
  document.getElementById('ppMetrik').innerHTML = `
    <div style="padding:14px 0;text-align:center;border-right:1px solid var(--border)">
      <p style="font-size:22px;font-weight:700;color:var(--text)">${p.total_nik || 0}</p>
      <p style="font-size:11px;color:var(--muted)">NIK</p>
    </div>
    <div style="padding:14px 0;text-align:center;border-right:1px solid var(--border)">
      <p style="font-size:22px;font-weight:700;color:var(--text)">${p.total_txn || 0}</p>
      <p style="font-size:11px;color:var(--muted)">Transaksi</p>
    </div>
    <div style="padding:14px 0;text-align:center;border-right:1px solid var(--border)">
      <p style="font-size:22px;font-weight:700;color:#10B981">${p.total_tabung || 0}</p>
      <p style="font-size:11px;color:var(--muted)">Tabung</p>
    </div>
    <div style="padding:14px 0;text-align:center">
      <p style="font-size:22px;font-weight:700;color:${p.has_violation ? '#EF4444' : 'var(--text)'}">
        ${Object.values(p.violations || {}).reduce((a,b)=>a+b,0) || 0}
      </p>
      <p style="font-size:11px;color:var(--muted)">Pelanggaran</p>
    </div>
  `;

  // Stok
  const stokHtml = p.stok_redeem > 0
    ? `<div style="display:flex;gap:20px;flex-wrap:wrap">
        <div><p style="font-size:11px;color:var(--muted)">Stok Sisa</p><p style="font-size:16px;font-weight:600;color:var(--text)">${p.stok_available}</p></div>
        <div><p style="font-size:11px;color:var(--muted)">Total Disalurkan</p><p style="font-size:16px;font-weight:600;color:#8B5CF6">${p.stok_redeem}</p></div>
        <div><p style="font-size:11px;color:var(--muted)">Terjual</p><p style="font-size:16px;font-weight:600;color:#10B981">${p.sold}</p></div>
      </div>`
    : `<p style="font-size:12px;color:var(--muted)">Data stok belum tersedia (jalankan scraping)</p>`;
  document.getElementById('ppStok').innerHTML = `
    <p style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Data Stok</p>
    ${stokHtml}
  `;

  // Per tipe konsumen
  const tipe = p.per_tipe || {};
  const tipeHtml = Object.keys(tipe).length
    ? Object.entries(tipe).map(([kat, d]) => `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--bg);border-radius:8px">
          <span style="font-size:13px;color:var(--text)">${kat}</span>
          <div style="display:flex;gap:16px">
            <span style="font-size:12px;color:var(--muted)">${d.count} transaksi</span>
            <span style="font-size:13px;font-weight:600;color:var(--accent)">${d.tabung} tabung</span>
          </div>
        </div>`).join('')
    : '<p style="font-size:12px;color:var(--muted)">Tidak ada data transaksi di periode ini</p>';
  document.getElementById('ppTipe').innerHTML = tipeHtml;

  // Pelanggaran
  const violations = p.violations || {};
  const violHtml = Object.keys(violations).length
    ? `<p style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Detail Pelanggaran</p>` +
      Object.entries(violations).map(([type, count]) => `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#FEF2F2;border-radius:8px;margin-bottom:6px">
          <span style="font-size:13px;color:#991B1B">⚠ ${violationLabels[type] || type}</span>
          <span style="background:#EF4444;color:#fff;font-size:11px;padding:1px 8px;border-radius:99px;font-weight:600">${count}×</span>
        </div>`).join('')
    : `<p style="font-size:12px;color:#10B981">✓ Tidak ada pelanggaran di periode ini</p>`;
  document.getElementById('ppViolations').innerHTML = violHtml;

  // Tampilkan overlay
  const overlay = document.getElementById('pangkalanPopupOverlay');
  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closePangkalanPopup() {
  document.getElementById('pangkalanPopupOverlay').style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePangkalanPopup(); });
</script>
@endpush