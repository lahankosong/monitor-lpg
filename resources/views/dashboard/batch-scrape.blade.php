@extends('layouts.app')
@section('title', 'Scrape Data')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Scrape Data MAP</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Dua mode: <strong>Login otomatis</strong> via Playwright (berat, perlu browser) atau
      <strong>Scrape ringan</strong> pakai token yang sudah tersimpan.
    </p>
  </div>
  <a href="{{ route('dashboard.akun.index') }}"
     style="border:1px solid var(--border);color:var(--text);background:var(--surface);
            border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
    ⚙ Kelola Akun →
  </a>
</div>

@if(session('success'))
<div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:10px;
            padding:12px 16px;margin-bottom:16px;font-size:13px;color:#065F46;font-weight:500">
  ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;
            padding:12px 16px;margin-bottom:16px;font-size:13px;color:#991B1B">
  @foreach($errors->all() as $e) ✗ {{ $e }}<br> @endforeach
</div>
@endif

{{-- Status cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">

  <div class="stat-card" style="border-left:3px solid {{ $hasScript ? '#059669' : '#DC2626' }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Script Playwright</p>
    <p style="font-size:15px;font-weight:700;color:var(--text);margin-top:4px">
      {{ $hasScript ? '✓ Tersedia' : '✗ Tidak Ada' }}
    </p>
    <p style="font-size:11px;color:var(--muted)">auto_login_batch.py</p>
  </div>

  <div class="stat-card" style="border-left:3px solid {{ $hasAccounts ? '#059669' : '#DC2626' }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Akun di Database</p>
    <p style="font-size:22px;font-weight:700;color:var(--text);margin-top:4px">{{ $accounts->count() }}</p>
    <p style="font-size:11px;color:var(--muted)">akun aktif siap scrape</p>
  </div>

  @php
    $tokenAktif  = \App\Models\PangkalanToken::where('is_active',true)
                     ->where('token_expires_at','>',now()->utc())->count();
    $tokenExpired = \App\Models\PangkalanToken::where('is_active',true)
                     ->where('token_expires_at','<=',now()->utc())->count();
  @endphp
  @php
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

  <div class="stat-card" style="border-left:3px solid {{ $tokenAktif > 0 ? '#059669' : '#DC2626' }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Token Aktif</p>
    <p style="font-size:22px;font-weight:700;color:{{ $tokenAktif > 0 ? 'var(--stat-positive,#059669)' : '#DC2626' }};margin-top:4px">
      {{ $tokenAktif }}
    </p>
    <p style="font-size:11px;color:var(--muted)">
      siap scrape ringan
      @if($tokenExpired > 0)
        · <span style="color:#DC2626">{{ $tokenExpired }} expired</span>
      @endif
    </p>
  </div>

</div>

{{-- ══ MODE 1: SCRAPE RINGAN (pakai token tersimpan) ══ --}}
<div class="card" style="padding:20px;margin-bottom:16px;border-left:4px solid var(--stat-positive,#059669)">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
    <div>
      <h2 style="font-size:14px;font-weight:700;color:var(--text)">
        ⚡ Mode Ringan — Pakai Token Tersimpan
      </h2>
      <p style="font-size:12px;color:var(--muted);margin-top:2px">
        Tidak butuh browser. Hanya HTTP request. Estimasi ~2 detik/pangkalan.
        Token di-refresh otomatis via GitHub Actions.
      </p>
    </div>
    <span style="font-size:11px;padding:3px 10px;border-radius:99px;
                 background:#D1FAE5;color:#065F46;font-weight:600;white-space:nowrap">
      Direkomendasikan
    </span>
  </div>

  @if($tokenAktif > 0)
  <form action="{{ route('dashboard.batch.scrape-ringan') }}" method="POST">
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
      <button type="submit"
              style="background:var(--stat-positive,#059669);color:#fff;border:none;
                     border-radius:8px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer">
        ⚡ Scrape Ringan
      </button>
    </div>
  </form>
  <p style="font-size:11px;color:var(--muted);margin-top:8px">
    Atau via terminal: <code style="background:var(--bg);padding:2px 6px;border-radius:4px;font-family:monospace">
      php artisan scrape:transaksi --from=YYYY-MM-DD --to=YYYY-MM-DD
    </code>
  </p>
  @else
  <div style="background:#FEF3C7;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400E">
    ⚠ Belum ada token aktif. GitHub Actions akan refresh token otomatis 2x sehari (06:00 & 18:00 WIB),
    atau jalankan manual dari tab Actions di GitHub.
    <br>Cek status: <code style="font-family:monospace">php artisan token:status</code>
  </div>
  @endif
</div>

{{-- ══ MODE 2: BATCH SCRAPE (Playwright login) ══ --}}
<div class="card" style="padding:20px;margin-bottom:20px">
  <h2 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">
    🚀 Mode Lengkap — Login + Scrape (Playwright)
  </h2>
  <p style="font-size:12px;color:var(--muted);margin-bottom:14px">
    Login ke MyPertamina via browser otomatis, ambil token baru, lalu scrape transaksi.
    Lebih berat (~30 detik/pangkalan). Gunakan jika token sudah expired semua.
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
              style="background:var(--accent);color:#fff;border:none;border-radius:8px;
                     padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;
                     white-space:nowrap;opacity:{{ (!$hasScript || !$hasAccounts) ? '0.4' : '1' }}">
        🚀 Jalankan
      </button>
    </div>
    <p id="loadingMsg" style="display:none;font-size:12px;color:var(--muted);margin-top:10px;text-align:center">
      ⏳ Sedang login dan scraping {{ $accounts->count() }} pangkalan via Playwright...
    </p>
  </form>

  @if(!$hasScript)
  <div style="background:#FEF3C7;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:12px;color:#92400E">
    ⚠ Script <code>scripts/auto_login_batch.py</code> tidak ditemukan di server ini.
    Mode ini hanya bisa dijalankan dari laptop/VPS yang punya Python + Playwright.
  </div>
  @endif
</div>

{{-- Daftar akun dari DB --}}
@if($hasAccounts)
<div class="card" style="overflow:hidden;margin-bottom:16px">
  <div style="padding:12px 18px;border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">
      Akun Pangkalan ({{ $accounts->count() }})
    </h2>
    <span style="font-size:11px;color:var(--muted)">Sumber: database — bukan accounts.json</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['#','Nama Pangkalan','Email / No HP','Scrape Terakhir'] as $h)
          <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
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

{{-- Status token per pangkalan --}}
@php
  $tokens = \App\Models\PangkalanToken::orderBy('token_expires_at')->get();
@endphp
@if($tokens->isNotEmpty())
<div class="card" style="overflow:hidden;margin-bottom:20px">
  <div style="padding:12px 18px;border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Status Token per Pangkalan</h2>
    <span style="font-size:11px;color:var(--muted)">
      {{ $tokenAktif }} aktif · {{ $tokenExpired }} expired
    </span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','Token Berlaku s/d','Status','Scrape Terakhir'] as $h)
          <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
                     color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($tokens as $t)
      @php
        $expired = $t->token_expires_at?->isPast();
        $hampir  = !$expired && $t->token_expires_at?->diffInHours(now()) <= 2;
        $lastLog = \App\Models\ScrapeLog::where('pangkalan_id', $t->pangkalan_id)
                     ->latest('scraped_at')->first();
      @endphp
      <tr style="border-top:1px solid var(--border){{ $expired ? ';background:rgba(220,38,38,.03)' : '' }}">
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">
          {{ $t->label ?? $t->pangkalan_id }}
        </td>
        <td style="padding:9px 14px;font-size:12px;
                   color:{{ $expired ? '#DC2626' : ($hampir ? '#F59E0B' : 'var(--muted)') }}">
          {{ $t->token_expires_at?->format('d/m/Y H:i') ?? '—' }}
          @if($hampir) <span style="font-size:10px">(hampir expired)</span>@endif
        </td>
        <td style="padding:9px 14px">
          @if($expired)
            <span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:99px;font-size:11px">Expired</span>
          @else
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:11px">✓ Aktif</span>
          @endif
        </td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">
          @if($lastLog)
            {{ $lastLog->scraped_at->format('d/m H:i') }}
            ({{ $lastLog->records_saved }} txn)
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
  <div style="padding:12px 18px;border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Log Scraping Terbaru</h2>
    <a href="{{ route('dashboard.nik.list') }}"
       style="font-size:12px;color:var(--accent);text-decoration:none">Lihat data NIK →</a>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','Periode','Status','Diambil','Disimpan','Waktu'] as $h)
          <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
                     color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($lastLogs as $log)
      @php $token = \App\Models\PangkalanToken::where('pangkalan_id', $log->pangkalan_id)->first(); @endphp
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
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:11px">Berhasil</span>
          @else
            <span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:99px;font-size:11px">Gagal</span>
          @endif
        </td>
        <td style="padding:9px 14px;text-align:right;font-size:12px;color:var(--muted)">{{ $log->records_fetched }}</td>
        <td style="padding:9px 14px;text-align:right;font-size:12px;font-weight:700;color:var(--text)">{{ $log->records_saved }}</td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">
          {{ \Carbon\Carbon::parse($log->scraped_at)->format('d/m H:i') }}
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" style="padding:48px;text-align:center;color:var(--muted)">Belum ada log scraping.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('formBatch')?.addEventListener('submit', function() {
  document.getElementById('btnBatch').disabled = true;
  document.getElementById('loadingMsg').style.display = 'block';
});
</script>
@endpush
