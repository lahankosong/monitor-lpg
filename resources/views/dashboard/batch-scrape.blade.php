@extends('layouts.app')
@section('title', 'Batch Scrape')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Batch Scrape</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Login otomatis ke MyPertamina, ambil token, scrape transaksi semua pangkalan.
    </p>
  </div>
  <a href="{{ route('dashboard.akun.index') }}"
     style="border:1px solid var(--border);color:var(--text);background:var(--surface);
            border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
    ⚙ Kelola Akun Pangkalan →
  </a>
</div>

@if(session('success'))
<div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:10px;padding:12px 16px;
            margin-bottom:16px;font-size:13px;color:#065F46;font-weight:500">
  ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;
            margin-bottom:16px;font-size:13px;color:#991B1B">
  @foreach($errors->all() as $e) ✗ {{ $e }}<br> @endforeach
</div>
@endif

{{-- Status sistem --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px">

  <div class="stat-card" style="border-left:3px solid {{ $hasScript ? '#059669' : '#DC2626' }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Script Playwright</p>
    <p style="font-size:16px;font-weight:700;color:var(--text);margin-top:4px">
      {{ $hasScript ? '✓ Tersedia' : '✗ Tidak Ada' }}
    </p>
    <p style="font-size:11px;color:var(--muted)">auto_login_batch.py</p>
  </div>

  <div class="stat-card" style="border-left:3px solid {{ $hasAccounts ? '#059669' : '#DC2626' }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Akun di Database</p>
    <p style="font-size:22px;font-weight:700;color:var(--text);margin-top:4px">
      {{ $accounts->count() }}
    </p>
    <p style="font-size:11px;color:var(--muted)">akun aktif siap scrape</p>
  </div>

  @php
    $siapScrape  = $accounts->count();
    $passTidakAda = \App\Models\PangkalanSession::where('is_active', true)
                    ->whereNull('password_encrypted')->count();
  @endphp
  @if($passTidakAda > 0)
  <div class="stat-card" style="border-left:3px solid #F59E0B">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Password Kosong</p>
    <p style="font-size:22px;font-weight:700;color:#F59E0B;margin-top:4px">{{ $passTidakAda }}</p>
    <p style="font-size:11px;color:var(--muted)">akun belum ada PIN</p>
  </div>
  @endif

</div>

{{-- Form jalankan batch --}}
<div class="card" style="padding:20px;margin-bottom:20px">
  <h2 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">
    Jalankan Batch Scrape
  </h2>
  <p style="font-size:12px;color:var(--muted);margin-bottom:16px">
    Playwright akan login ke <strong>{{ $accounts->count() }} pangkalan</strong> satu per satu.
    Estimasi: ~{{ $accounts->count() * 30 }} detik.
    Credentials diambil langsung dari database.
  </p>

  <form action="{{ route('dashboard.batch.run') }}" method="POST" id="formBatch">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:flex-end">
      <div>
        <label style="display:block;font-size:11px;color:var(--muted);font-weight:600;margin-bottom:5px">Dari</label>
        <input type="date" name="from" value="{{ now()->startOfWeek()->toDateString() }}"
               style="width:100%;border:1px solid var(--border);background:var(--surface);
                      color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
      </div>
      <div>
        <label style="display:block;font-size:11px;color:var(--muted);font-weight:600;margin-bottom:5px">Sampai</label>
        <input type="date" name="to" value="{{ now()->toDateString() }}"
               style="width:100%;border:1px solid var(--border);background:var(--surface);
                      color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
      </div>
      <button type="submit" id="btnBatch"
              {{ (!$hasScript || !$hasAccounts) ? 'disabled' : '' }}
              style="background:var(--accent);color:#151F28;border:none;border-radius:8px;
                     padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;
                     white-space:nowrap;opacity:{{ (!$hasScript || !$hasAccounts) ? '0.4' : '1' }}">
        🚀 Jalankan
      </button>
    </div>
    <p id="loadingMsg" style="display:none;font-size:12px;color:var(--muted);margin-top:10px;text-align:center">
      ⏳ Sedang login dan scraping {{ $accounts->count() }} pangkalan... Mohon tunggu.
    </p>
  </form>

  @if(!$hasScript)
  <div style="background:#FEF3C7;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:12px;color:#92400E">
    ⚠ Script <code>scripts/auto_login_batch.py</code> tidak ditemukan.
  </div>
  @endif
  @if(!$hasAccounts)
  <div style="background:#FEE2E2;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:12px;color:#991B1B">
    ✗ Belum ada akun aktif di database.
    <a href="{{ route('dashboard.akun.index') }}" style="color:#DC2626;font-weight:600">Tambah akun →</a>
  </div>
  @endif
</div>

{{-- Daftar akun dari DB --}}
@if($hasAccounts)
<div class="card" style="overflow:hidden;margin-bottom:20px">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">
      Akun Pangkalan ({{ $accounts->count() }})
    </h2>
    <span style="font-size:11px;color:var(--muted)">Sumber: database — bukan accounts.json</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['#','Nama Pangkalan','Email / No HP','Status Terakhir'] as $h)
          <th style="text-align:left;padding:8px 14px;font-size:11px;font-weight:600;
                     color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($accounts as $i => $acc)
      @php
        $session = \App\Models\PangkalanSession::where('username', $acc['email'])->first();
        $lastLog = $session
          ? \App\Models\ScrapeLog::where('pangkalan_id', $session->pangkalan_id)
              ->latest('scraped_at')->first()
          : null;
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;color:var(--muted)">{{ $i + 1 }}</td>
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">{{ $acc['label'] ?? '—' }}</td>
        <td style="padding:9px 14px;color:var(--muted);font-size:12px">{{ $acc['email'] }}</td>
        <td style="padding:9px 14px;font-size:12px">
          @if($lastLog)
            <span style="color:{{ $lastLog->status === 'success' ? '#059669' : '#DC2626' }}">
              {{ $lastLog->status === 'success' ? '✓' : '✗' }}
              {{ $lastLog->scraped_at->format('d/m H:i') }}
              ({{ $lastLog->records_saved }} txn)
            </span>
          @else
            <span style="color:var(--muted)">Belum pernah</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- Log scraping terbaru --}}
<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Log Scraping Terbaru</h2>
    <a href="{{ route('dashboard.nik.list') }}"
       style="font-size:12px;color:var(--accent);text-decoration:none">Lihat data NIK →</a>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','Periode','Status','Diambil','Disimpan','Waktu'] as $h)
          <th style="text-align:left;padding:8px 14px;font-size:11px;font-weight:600;
                     color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($lastLogs as $log)
      @php
        $token = \App\Models\PangkalanToken::where('pangkalan_id', $log->pangkalan_id)->first();
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">
          {{ $token?->label ?? substr($log->pangkalan_id ?? '—', 0, 14) }}
        </td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">
          {{ \Carbon\Carbon::parse($log->start_date)->format('d/m') }} s/d
          {{ \Carbon\Carbon::parse($log->end_date)->format('d/m') }}
        </td>
        <td style="padding:9px 14px">
          @if($log->status === 'success')
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500">
              Berhasil
            </span>
          @else
            <span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500">
              Gagal
            </span>
          @endif
        </td>
        <td style="padding:9px 14px;text-align:right;font-size:12px;color:var(--muted)">
          {{ $log->records_fetched }}
        </td>
        <td style="padding:9px 14px;text-align:right;font-size:12px;font-weight:700;color:var(--text)">
          {{ $log->records_saved }}
        </td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">
          {{ \Carbon\Carbon::parse($log->scraped_at)->format('d/m H:i') }}
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" style="padding:48px;text-align:center;color:var(--muted)">
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
  document.getElementById('loadingMsg').style.display = 'block';
});
</script>
@endpush
