{{-- resources/views/layouts/partials/sidebar.blade.php --}}
{{-- Update HANYA file ini saat ada perubahan menu --}}

<div class="sidebar-label" style="padding: 8px 20px 6px">MAP</div>
<div class="sidebar-section">
  <a href="{{ route('dashboard.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.index') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <span class="sidebar-item-text">Dashboard</span>
  </a>
  <a href="{{ route('dashboard.nik.list') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.nik.*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <span class="sidebar-item-text">Monitor NIK</span>
    @php $alertCount = \Illuminate\Support\Facades\Cache::remember('nik_alert_count', 300, fn() => \App\Models\NikViolation::count() ?? 0); @endphp
    @if($alertCount > 0)
      <span style="margin-left:auto;background:#EF4444" class="sidebar-badge;color:#fff;font-size:10px;padding:1px 6px;border-radius:99px">{{ $alertCount }}</span>
    @endif
  </a>
  <a href="{{ route('dashboard.akun.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.map.*') ? 'active' : '' }}">
    MAP Analytics
  </a>
  <a href="#" class="sidebar-item ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    <span class="sidebar-item-text">MAP Analytics</span>
  </a>
  <a href="{{ route('dashboard.akun.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.akun.*','dashboard.batch.*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
    <span class="sidebar-item-text">Scrape Data</span>
  </a>
</div>

@if(!auth()->user()?->isDriver())
<div class="sidebar-label" style="padding: 16px 20px 6px">Operasional</div>
<div class="sidebar-section">
  <a href="{{ route('dashboard.agen.operasional.kitir.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.operasional.kitir*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
    <span class="sidebar-item-text">Kitir</span>
  </a>
  <a href="{{ route('dashboard.agen.operasional.sj.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.operasional.sj*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
    <span class="sidebar-item-text">Surat Jalan</span>
  </a>
</div>
@endif

<div class="sidebar-label" style="padding: 16px 20px 6px">Distribusi</div>
<div class="sidebar-section">
  @if(!auth()->user()?->isDriver())
  <a href="{{ route('dashboard.agen.distribusi.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.distribusi.index') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    <span class="sidebar-item-text">Realisasi</span>
  </a>

  <a href="{{ route('dashboard.agen.distribusi.gudang.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.distribusi.gudang*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="9" y="12" width="6" height="9" rx="0"/></svg>
    <span class="sidebar-item-text">Gudang</span>
    @php $saldoGudang = \Illuminate\Support\Facades\DB::table('gudang_stok')->sum('sisa_stok') @endphp
    @if($saldoGudang > 0)
      <span style="margin-left:auto;background:var(--accent)" class="sidebar-badge;color:#fff;font-size:10px;padding:1px 6px;border-radius:99px">
        {{ number_format($saldoGudang) }}
      </span>
    @endif
  </a>
  @endif
  <a href="{{ route('dashboard.agen.driver.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.driver*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
    <span class="sidebar-item-text">Mode Driver</span>
  </a>
</div>

@if(!auth()->user()?->isDriver())
<div class="sidebar-label" style="padding: 16px 20px 6px">Akuntansi</div>
<div class="sidebar-section">
  <a href="{{ route('dashboard.agen.akuntansi.harga.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.harga*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 7h10M7 12h10M7 17h5"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
    <span class="sidebar-item-text">Referensi Harga</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.tebusan.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.tebusan*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    <span class="sidebar-item-text">Tebusan</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.brimola.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.brimola.index') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    <span class="sidebar-item-text">BRImola</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.brimola.audit.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.brimola.audit*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11H6a3 3 0 0 0-3 3v3a3 3 0 0 0 3 3h3"/><path d="M15 13l2 2 4-4"/><circle cx="12" cy="6" r="4"/></svg>
    <span class="sidebar-item-text">Audit Pembayaran</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.piutang.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.piutang*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    <span class="sidebar-item-text">Piutang Kerjasama</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.buku-besar.jurnal') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.buku-besar.jurnal*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    <span class="sidebar-item-text">Jurnal Umum</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.buku-besar.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.buku-besar.index') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    <span class="sidebar-item-text">Buku Besar</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.buku-besar.laba-rugi') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.buku-besar.laba-rugi') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    <span class="sidebar-item-text">Laba Rugi</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.buku-besar.neraca') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.buku-besar.neraca') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
    <span class="sidebar-item-text">Neraca</span>
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.kas.index') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.akuntansi.kas*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
    <span class="sidebar-item-text">Kas Kecil</span>
  </a>
</div>

<div class="sidebar-label" style="padding: 16px 20px 6px">Database</div>
<div class="sidebar-section">
  <a href="{{ route('dashboard.agen.db.agen') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.db.agen*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span class="sidebar-item-text">Profil Agen</span>
  </a>
  <a href="{{ route('dashboard.agen.db.spbe') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.db.spbe*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
    <span class="sidebar-item-text">SPBE</span>
  </a>
  <a href="{{ route('dashboard.agen.db.pangkalan') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.db.pangkalan*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    <span class="sidebar-item-text">Pangkalan</span>
  </a>
  <a href="{{ route('dashboard.agen.db.karyawan') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.db.karyawan*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <span class="sidebar-item-text">Karyawan</span>
  </a>
  <a href="{{ route('dashboard.agen.db.armada') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.agen.db.armada*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    <span class="sidebar-item-text">Armada</span>
  </a>
</div>

<div class="sidebar-label" style="padding: 16px 20px 6px">Sistem</div>
<div class="sidebar-section">
  <a href="{{ route('dashboard.token.input') }}"
     class="sidebar-item {{ request()->routeIs('dashboard.token.*') ? 'active' : '' }}">
    <svg class="sidebar-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span class="sidebar-item-text">Token</span>
  </a>
</div>
@endif