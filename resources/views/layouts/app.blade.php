<!DOCTYPE html>
<html lang="id" data-theme="cerah">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Rawarun Tech — @yield('title', 'Dashboard')</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
/* ── Theme Variables ─────────────────────────────────── */
/* ── TEMA CERAH: Biru Langit + Orange + Hijau Sage ──── */

/* ── Auto dark mode dari system preference ─────────── */
/* Aktif ketika pilihan tema = "system" dan OS pakai dark mode */
@media (prefers-color-scheme: dark) {
  [data-theme="system"] {
    --bg:               #070F1C;
    --surface:          #0D1B2E;
    --surface-alt:      #112338;
    --border:           #1E3A5F;
    --nav-bg:           #0D1B2E;
    --nav-text:         #7CA4C4;
    --nav-active:       #38BDF8;
    --accent:           #0EA5E9;
    --accent-2:         #FB923C;
    --accent-3:         #7DB87F;
    --text:             #E2F0FB;
    --muted:            #4A7090;
    --sidebar:          #050D18;
    --sidebar-text:     #6B9EC0;
    --sidebar-active:   #38BDF8;
    --sidebar-accent:   #FB923C;
    --radius:           8px;
    --stat-positive:    #7DB87F;
    --stat-negative:    #F87171;
    --stat-warning:     #FB923C;
    --stat-info:        #38BDF8;
  }
}
@media (prefers-color-scheme: light) {
  [data-theme="system"] {
    --bg:               #F0F7FF;
    --surface:          #FFFFFF;
    --surface-alt:      #E8F4FD;
    --border:           #BAD4EF;
    --nav-bg:           #FFFFFF;
    --nav-text:         #334155;
    --nav-active:       #0EA5E9;
    --accent:           #0EA5E9;
    --accent-2:         #F97316;
    --accent-3:         #6B9E6E;
    --text:             #0F2942;
    --muted:            #5A7A9A;
    --sidebar:          #0C2340;
    --sidebar-text:     #A8C5E0;
    --sidebar-active:   #38BDF8;
    --sidebar-accent:   #F97316;
    --radius:           8px;
    --stat-positive:    #6B9E6E;
    --stat-negative:    #EF4444;
    --stat-warning:     #F97316;
    --stat-info:        #0EA5E9;
  }
}
:root,
[data-theme="cerah"] {
  --bg:               #F0F7FF;        /* biru langit sangat muda */
  --surface:          #FFFFFF;
  --surface-alt:      #E8F4FD;        /* card sekunder biru muda */
  --border:           #BAD4EF;        /* biru langit medium */
  --nav-bg:           #FFFFFF;
  --nav-text:         #334155;
  --nav-active:       #0EA5E9;        /* biru langit cerah */
  --accent:           #0EA5E9;        /* biru langit — CTA utama */
  --accent-2:         #F97316;        /* orange — highlight penting */
  --accent-3:         #6B9E6E;        /* hijau sage — status positif */
  --text:             #0F2942;        /* biru tua gelap — teks utama */
  --muted:            #5A7A9A;        /* biru abu — teks sekunder */
  --sidebar:          #0C2340;        /* navy gelap — sidebar selalu dark */
  --sidebar-text:     #A8C5E0;
  --sidebar-active:   #38BDF8;        /* biru langit terang */
  --sidebar-accent:   #F97316;        /* orange untuk badge/notif */
  --radius:           8px;
  --stat-positive:    #6B9E6E;        /* hijau sage */
  --stat-negative:    #EF4444;
  --stat-warning:     #F97316;        /* orange */
  --stat-info:        #0EA5E9;        /* biru langit */
}

/* ── TEMA GELAP: Navy + Biru Dingin ─────────────────── */
[data-theme="dark"] {
  --bg:               #070F1C;        /* hitam navy dalam */
  --surface:          #0D1B2E;        /* navy gelap */
  --surface-alt:      #112338;        /* sedikit lebih terang */
  --border:           #1E3A5F;        /* biru tua */
  --nav-bg:           #0D1B2E;
  --nav-text:         #7CA4C4;
  --nav-active:       #38BDF8;
  --accent:           #0EA5E9;
  --accent-2:         #FB923C;        /* orange lebih terang di dark */
  --accent-3:         #7DB87F;        /* sage lebih terang */
  --text:             #E2F0FB;        /* biru sangat muda */
  --muted:            #4A7090;
  --sidebar:          #050D18;        /* hitam navy hampir pekat */
  --sidebar-text:     #6B9EC0;
  --sidebar-active:   #38BDF8;
  --sidebar-accent:   #FB923C;
  --radius:           8px;
  --stat-positive:    #7DB87F;
  --stat-negative:    #F87171;
  --stat-warning:     #FB923C;
  --stat-info:        #38BDF8;
}

/* ── TEMA CLASSIC: Hijau Alam (tetap) ───────────────── */
[data-theme="classic"] {
  --bg:               #F5F0E8;
  --surface:          #FDF8F0;
  --surface-alt:      #EDE8DC;
  --border:           #D4B896;
  --nav-bg:           #2C4A2E;
  --nav-text:         #D4E8D5;
  --nav-active:       #FFD700;
  --accent:           #2C4A2E;
  --accent-2:         #D4880A;
  --accent-3:         #4A7A4C;
  --text:             #1A1A1A;
  --muted:            #6B5E4E;
  --sidebar:          #1E3420;
  --sidebar-text:     #B8D4B9;
  --sidebar-active:   #FFD700;
  --sidebar-accent:   #FFD700;
  --radius:           8px;
  --stat-positive:    #4A7A4C;
  --stat-negative:    #C0392B;
  --stat-warning:     #D4880A;
  --stat-info:        #2C4A2E;
}

/* ── TEMA MODERN: Indigo-Ungu (tetap) ───────────────── */
[data-theme="modern"] {
  --bg:               #F0F4FF;
  --surface:          #FFFFFF;
  --surface-alt:      #E8ECFF;
  --border:           #C7D2FE;
  --nav-bg:           linear-gradient(135deg,#667EEA,#764BA2);
  --nav-text:         rgba(255,255,255,0.85);
  --nav-active:       #FFFFFF;
  --accent:           #667EEA;
  --accent-2:         #F59E0B;
  --accent-3:         #10B981;
  --text:             #1E1B4B;
  --muted:            #6366F1;
  --sidebar:          #312E81;
  --sidebar-text:     rgba(255,255,255,0.65);
  --sidebar-active:   #FFFFFF;
  --sidebar-accent:   #F59E0B;
  --radius:           8px;
  --stat-positive:    #10B981;
  --stat-negative:    #EF4444;
  --stat-warning:     #F59E0B;
  --stat-info:        #667EEA;
}

/* ── Base ────────────────────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: system-ui,-apple-system,sans-serif;
  font-size: 14px;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ── Top Navbar ──────────────────────────────────────── */
#topnav {
  background: var(--nav-bg);
  border-bottom: 1px solid var(--border);
  height: 56px;
  display: flex;
  align-items: center;
  padding: 0 20px;
  gap: 0;
  position: sticky;
  top: 0;
  z-index: 200;
  flex-shrink: 0;
}
@media (max-width: 768px) {
  #topnav {
    padding: 0 10px;
    height: 44px;
  }
  #topnav .desktop-only { display: none !important; }
  #topnav .nav-group { display: none !important; }
  #topnav .theme-pills { display: none !important; }
}index: 100;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
[data-theme="modern"] #topnav {
  background: linear-gradient(135deg,#667EEA,#764BA2);
  border-bottom: none;
}
[data-theme="classic"] #topnav {
  background: #2C4A2E;
  border-bottom: 2px solid #D4B896;
}
[data-theme="dark"] #topnav {
  background: #1E293B;
  border-bottom: 1px solid #334155;
}

.brand {
  font-size: 16px;
  font-weight: 700;
  color: var(--nav-active);
  text-decoration: none;
  white-space: nowrap;
  margin-right: 8px;
}
[data-theme="modern"] .brand,
[data-theme="classic"] .brand { color: #fff; }

/* Menu group (desktop) */
.nav-group {
  display: flex;
  align-items: center;
  height: 56px;
  position: relative;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: 5px;
  height: 56px;
  padding: 0 14px;
  font-size: 13px;
  color: var(--nav-text);
  text-decoration: none;
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  transition: color .15s, border-color .15s;
  cursor: pointer;
  background: none;
  border-top: none;
  border-left: none;
  border-right: none;
}
.nav-link:hover { color: var(--nav-active); }
.nav-link.active {
  color: var(--nav-active);
  border-bottom-color: var(--nav-active);
  font-weight: 500;
}
[data-theme="modern"] .nav-link,
[data-theme="classic"] .nav-link { color: rgba(255,255,255,.8); }
[data-theme="modern"] .nav-link.active,
[data-theme="classic"] .nav-link.active { color:#fff; border-bottom-color:#fff; }

/* Dropdown submenu */
.dropdown { position: relative; }
.dropdown-menu {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: 0 8px 24px rgba(0,0,0,.1);
  min-width: 220px;
  z-index: 200;
  overflow: hidden;
}
.dropdown:hover .dropdown-menu,
.dropdown.open .dropdown-menu { display: block; }
.dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  font-size: 13px;
  color: var(--text);
  text-decoration: none;
  transition: background .1s;
}
.dropdown-item:hover { background: var(--bg); }
.dropdown-item.active { color: var(--accent); font-weight: 500; }
.dropdown-sep { height: 1px; background: var(--border); margin: 4px 0; }
.dropdown-label {
  padding: 6px 16px 4px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .08em;
  color: var(--muted);
  text-transform: uppercase;
}

/* Right side nav */
.nav-right {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Theme switcher pills */
.theme-pills {
  display: flex;
  gap: 2px;
  background: rgba(0,0,0,.06);
  border-radius: 20px;
  padding: 2px;
}
[data-theme="dark"] .theme-pills { background: rgba(255,255,255,.08); }
[data-theme="modern"] .theme-pills,
[data-theme="classic"] .theme-pills { background: rgba(255,255,255,.15); }
.theme-pill {
  font-size: 10px;
  padding: 3px 9px;
  border-radius: 16px;
  cursor: pointer;
  border: none;
  background: transparent;
  color: var(--muted);
  transition: all .15s;
  font-weight: 500;
}
[data-theme="modern"] .theme-pill,
[data-theme="classic"] .theme-pill { color: rgba(255,255,255,.7); }
.theme-pill.active {
  background: var(--surface);
  color: var(--text);
  box-shadow: 0 1px 3px rgba(0,0,0,.12);
}
[data-theme="modern"] .theme-pill.active,
[data-theme="classic"] .theme-pill.active { background: rgba(255,255,255,.25); color:#fff; }

/* Batch indicator */
.batch-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #3B82F6;
  background: #EFF6FF;
  border-radius: 20px;
  padding: 4px 10px;
}
[data-theme="dark"] .batch-indicator { background: #1E3A5F; }
.batch-dot { width:7px; height:7px; border-radius:50%; background:#3B82F6; animation: pulse 1.2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── Layout: sidebar + content ───────────────────────── */
#layout { display: flex; flex: 1; min-height: 0; }

/* Sidebar (desktop only, hidden on mobile) */
#sidebar {
  width: 220px;
  background: var(--sidebar);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  padding: 16px 0;
  overflow-y: auto;
  transition: width .25s cubic-bezier(.4,0,.2,1);
  overflow-x: hidden;
}
#sidebar.collapsed { width: 52px; }
#sidebar.collapsed .sidebar-label { display: none; }
#sidebar.collapsed .sidebar-item-text { display: none; }
#sidebar.collapsed .sidebar-badge { display: none; }
#sidebar.collapsed .sidebar-item { justify-content: center; padding: 10px; }
#sidebar.collapsed .sidebar-icon { width: 20px; height: 20px; opacity: 1; }
#sidebar-toggle {
  display: flex; align-items: center; justify-content: flex-end;
  padding: 0 14px 10px; cursor: pointer; gap: 6px;
}
#sidebar.collapsed #sidebar-toggle { justify-content: center; padding: 0 0 10px; }
#sidebar-toggle svg { color:rgba(255,255,255,.35); transition: transform .25s; }
#sidebar.collapsed #sidebar-toggle svg { transform: rotate(180deg); }
.sidebar-section { padding: 0 12px; margin-bottom: 4px; }
.sidebar-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .08em;
  color: rgba(255,255,255,.35);
  padding: 12px 8px 4px;
  text-transform: uppercase;
}
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 9px 10px;
  border-radius: 8px;
  font-size: 13px;
  color: var(--sidebar-text);
  text-decoration: none;
  transition: background .15s, color .15s;
  cursor: pointer;
  border: none;
  background: transparent;
  width: 100%;
  text-align: left;
}
.sidebar-item:hover { background: rgba(255,255,255,.08); color: #fff; }
.sidebar-item.active {
  background: rgba(255,255,255,.12);
  color: var(--sidebar-active);
  font-weight: 500;
}
.sidebar-icon { width: 18px; height: 18px; opacity: .7; }
.sidebar-item.active .sidebar-icon { opacity: 1; }

/* Main content */
#content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

/* ── Mobile bottom nav ───────────────────────────────── */
#bottomnav {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: var(--surface);
  border-top: 1px solid var(--border);
  height: 60px;
  z-index: 100;
  box-shadow: 0 -2px 12px rgba(0,0,0,.08);
}
.bottomnav-inner {
  display: flex;
  height: 100%;
  align-items: center;
}
.bottom-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  color: var(--muted);
  text-decoration: none;
  font-size: 10px;
  height: 100%;
  border: none;
  background: none;
  cursor: pointer;
  transition: color .15s;
}
.bottom-item.active { color: var(--accent); }
.bottom-item svg { width:22px; height:22px; }
.bottom-more-menu {
  display: none;
  position: fixed;
  bottom: 64px; left: 0; right: 0;
  background: var(--surface);
  border-top: 1px solid var(--border);
  padding: 16px;
  z-index: 99;
  box-shadow: 0 -4px 20px rgba(0,0,0,.1);
  border-radius: 16px 16px 0 0;
}
.bottom-more-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
.bottom-more-item.active {
  background: rgba(14,165,233,.12);
  color: var(--accent);
}
.bottom-more-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  background: var(--bg);
  border-radius: 10px;
  text-decoration: none;
  color: var(--text);
  font-size: 11px;
  font-weight: 500;
  text-align: center;
}
.bottom-more-item svg { width:24px; height:24px; color: var(--accent); }

/* ── Badges ──────────────────────────────────────────── */
.badge-alert{background:#FEE2E2;color:#991B1B;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:500;display:inline-block}
.badge-warn {background:#FEF3C7;color:#92400E;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:500;display:inline-block}
.badge-aman {background:#D1FAE5;color:#065F46;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:500;display:inline-block}
.badge-new  {background:#DBEAFE;color:#1E40AF;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:500;display:inline-block}

/* ── Notification bar ────────────────────────────────── */
.notif-success { background:#F0FDF4; border-bottom:1px solid #BBF7D0; color:#166534; padding:8px 20px; font-size:13px; }
.notif-error   { background:#FEF2F2; border-bottom:1px solid #FECACA; color:#991B1B; padding:8px 20px; font-size:13px; }

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 768px) {
  #sidebar { display: none; }
  #bottomnav { display: block; }
  #content { padding: 12px; padding-bottom: 76px; }
  .nav-group.desktop-only { display: none; }
  .theme-pills { display: none; }
  .batch-indicator span { display: none; }
}
@media (min-width: 769px) {
  .mobile-only { display: none !important; }
}

/* ── Card & Table utils ──────────────────────────────── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
}
</style>
</head>
<body>

<!-- ── TOP NAVBAR ─────────────────────────────────────── -->
<nav id="topnav">
  <a href="{{ route('dashboard.index') }}" class="brand">RUNTech</a>
  @php $__agen = \App\Models\Agen::profil(); @endphp
  @if($__agen?->nama_agen)
  <div style="position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:8px;pointer-events:none">
    <span style="font-size:13px;font-weight:600;color:var(--nav-text);opacity:.9;white-space:nowrap">
      {{ $__agen->nama_agen }}
    </span>
  </div>
  @endif

  {{-- Desktop menu groups --}}
  <div class="nav-group desktop-only">
    <a href="{{ route('dashboard.index') }}"
       class="nav-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}">
      Dashboard
    </a>
  </div>

  {{-- Fitur dropdown --}}
  <div class="nav-group dropdown desktop-only">
    <button class="nav-link {{ request()->routeIs('dashboard.nik.*','dashboard.akun.*','dashboard.batch.*','dashboard.token.*') ? 'active' : '' }}">
      Fitur
      <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:2px">
        <path d="M2 4l4 4 4-4"/>
      </svg>
    </button>
    <div class="dropdown-menu" style="left:0">
      <div class="dropdown-label">MAP — MyPertamina</div>
      <a href="{{ route('dashboard.index') }}" class="dropdown-item {{ request()->routeIs('dashboard.index') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard MAP
      </a>
      <a href="{{ route('dashboard.nik.list') }}" class="dropdown-item {{ request()->routeIs('dashboard.nik.*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Monitor NIK
      </a>
      <a href="{{ route('dashboard.akun.index') }}" class="dropdown-item {{ request()->routeIs('dashboard.akun.*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Scrape Data
        @php $jmlAkun = \App\Models\PangkalanSession::where('is_active',true)->count(); @endphp
        @if($jmlAkun)
          <span style="margin-left:auto;background:var(--accent);color:#fff;font-size:10px;padding:1px 6px;border-radius:99px">{{ $jmlAkun }}</span>
        @endif
      </a>
      <div class="dropdown-sep"></div>
      <div class="dropdown-label">Database</div>
      <a href="{{ route('dashboard.agen.db.agen') }}" class="dropdown-item {{ request()->routeIs('dashboard.agen.db.agen*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Profil Agen
      </a>
      <a href="{{ route('dashboard.agen.db.spbe') }}" class="dropdown-item {{ request()->routeIs('dashboard.agen.db.spbe*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        SPBE
      </a>
      <a href="{{ route('dashboard.agen.db.pangkalan') }}" class="dropdown-item {{ request()->routeIs('dashboard.agen.db.pangkalan*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Pangkalan
      </a>
      <a href="{{ route('dashboard.agen.db.karyawan') }}" class="dropdown-item {{ request()->routeIs('dashboard.agen.db.karyawan*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Karyawan
      </a>
      <a href="{{ route('dashboard.agen.db.armada') }}" class="dropdown-item {{ request()->routeIs('dashboard.agen.db.armada*') ? 'active' : '' }}">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Armada
      </a>
    </div>
  </div>

  {{-- Right side --}}
  <div class="nav-right">
    {{-- Batch running indicator --}}
    @php $batchRunning = \Illuminate\Support\Facades\Cache::get('batch_scrape_running', false); @endphp
    <div id="batchIndicator" class="{{ $batchRunning ? '' : 'hidden' }} batch-indicator">
      <div class="batch-dot"></div>
      <span id="batchLabel">Scraping...</span>
      <form id="stopFormNav" action="{{ route('dashboard.batch.stop') }}" method="POST" style="margin:0">
        @csrf
        <button type="submit" style="background:#EF4444;color:#fff;border:none;border-radius:6px;padding:2px 8px;font-size:11px;cursor:pointer">
          Stop
        </button>
      </form>
    </div>

    {{-- Theme switcher --}}
    <div class="theme-pills">
      <button class="theme-pill active" data-t="cerah" style="display:flex;align-items:center;gap:4px">
        <span style="display:inline-flex;gap:2px;align-items:center">
          <span style="width:6px;height:6px;border-radius:50%;background:#0EA5E9;display:inline-block"></span>
          <span style="width:6px;height:6px;border-radius:50%;background:#F97316;display:inline-block"></span>
          <span style="width:6px;height:6px;border-radius:50%;background:#6B9E6E;display:inline-block"></span>
        </span>
        Cerah
      </button>
      <button class="theme-pill" data-t="dark" style="display:flex;align-items:center;gap:4px">
        <span style="width:6px;height:6px;border-radius:50%;background:#0EA5E9;display:inline-block"></span>
        Gelap
      </button>
      <button class="theme-pill" data-t="classic">Classic</button>
      <button class="theme-pill" data-t="modern">Modern</button>
      <button class="theme-pill" data-t="system" title="Ikuti tema sistem (OS dark/light mode)" style="display:flex;align-items:center;gap:3px">
        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        Auto
      </button>
    </div>

    {{-- Mobile hamburger --}}
    <button class="mobile-only" onclick="toggleMobileMore()"
            style="background:none;border:none;cursor:pointer;padding:6px;color:var(--nav-text)">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    {{-- Notifikasi bell --}}
    @auth
    <div style="position:relative;display:flex;align-items:center">
      <button id="btn-notif" onclick="toggleNotifDropdown()"
              style="position:relative;background:none;border:none;cursor:pointer;
                     padding:6px;color:var(--nav-text);border-radius:8px;
                     transition:background .15s"
              onmouseover="this.style.background='rgba(0,0,0,.05)'"
              onmouseout="this.style.background='none'">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span id="notif-badge" style="display:none;position:absolute;top:2px;right:2px;
              background:#DC2626;color:#fff;font-size:9px;font-weight:700;
              min-width:16px;height:16px;border-radius:8px;
              display:none;align-items:center;justify-content:center;padding:0 3px">
          0
        </span>
      </button>

      {{-- Dropdown notifikasi --}}
      <div id="notif-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);right:0;
           background:var(--surface);border:1px solid var(--border);border-radius:12px;
           width:340px;box-shadow:0 8px 32px rgba(0,0,0,.15);z-index:200;overflow:hidden">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);
                    display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:13px;font-weight:700;color:var(--text)">Notifikasi</span>
          <button onclick="bacaSemuaNotif()"
                  style="font-size:11px;color:var(--accent);background:none;border:none;cursor:pointer">
            Tandai semua dibaca
          </button>
        </div>
        <div id="notif-list" style="max-height:320px;overflow-y:auto">
          <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">
            Memuat...
          </div>
        </div>
        <div style="padding:10px;border-top:1px solid var(--border);text-align:center">
          <a href="{{ route('dashboard.notifikasi.index') }}"
             style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:500">
            Lihat semua notifikasi →
          </a>
        </div>
      </div>
    </div>
    @endauth

    {{-- User info + logout --}}
    @auth
    <div class="desktop-only" style="display:flex;align-items:center;gap:8px">
      <div style="text-align:right">
        <div style="font-size:12px;font-weight:600;color:var(--nav-text);line-height:1.2">
          {{ auth()->user()->name }}
        </div>
        <div style="font-size:10px;padding:1px 6px;border-radius:99px;display:inline-block;margin-top:1px;
                    {{ \App\Models\User::ROLE_BADGE_COLOR[auth()->user()->role] ?? '' }}">
          {{ auth()->user()->role_label }}
        </div>
      </div>
      <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf
        <button type="submit"
                style="background:none;border:1px solid var(--border-color);color:var(--nav-text);
                       border-radius:7px;padding:5px 10px;font-size:11px;cursor:pointer;
                       font-family:inherit;opacity:.7;transition:opacity .15s"
                onmouseover="this.style.opacity='1'"
                onmouseout="this.style.opacity='.7'">
          Keluar
        </button>
      </form>
    </div>
    @endauth
  </div>
</nav>

<!-- Notification bars -->
@if(session('success'))
<div class="notif-success">✓ {{ session('success') }}</div>
@endif
@if($errors->any())
<div class="notif-error">@foreach($errors->all() as $e)✗ {{ $e }} @endforeach</div>
@endif

<!-- ── LAYOUT ──────────────────────────────────────────── -->
<div id="layout">

  <!-- Sidebar (desktop) -->
  <aside id="sidebar">
    {{-- Toggle collapse --}}
    <div id="sidebar-toggle" onclick="toggleSidebar()" title="Buka/tutup sidebar">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
    </div>
    @include('layouts.partials.sidebar')

    {{-- Batch status di sidebar --}}
    @if($batchRunning)
    <div style="margin:16px 12px 0;background:rgba(59,130,246,.12);border-radius:8px;padding:10px 12px">
      <div style="display:flex;align-items:center;gap:6px;color:#60A5FA;font-size:12px;font-weight:500">
        <div class="batch-dot" style="flex-shrink:0"></div>
        Scraping berjalan
      </div>
      <div id="sidebarProgressText" style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">
        Memuat progress...
      </div>
    </div>
    @endif
  </aside>

  <!-- Main content -->
  <main id="content">@yield('content')</main>
</div>

<!-- ── MOBILE BOTTOM NAV ──────────────────────────────── -->
<nav id="bottomnav">
  <div class="bottomnav-inner">
    @if(auth()->user()?->isDriver())
      {{-- Driver: menu khusus langsung ke halaman driver --}}
      <a href="{{ route('dashboard.agen.driver.index') }}"
         class="bottom-item {{ request()->routeIs('dashboard.agen.driver.index') ? 'active' : '' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Realisasi
      </a>
      <a href="{{ route('dashboard.agen.driver.histori') }}"
         class="bottom-item {{ request()->routeIs('dashboard.agen.driver.histori') ? 'active' : '' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
        Histori
      </a>
      <a href="{{ route('dashboard.agen.driver.stok') }}"
         class="bottom-item {{ request()->routeIs('dashboard.agen.driver.stok') ? 'active' : '' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Stok
      </a>
      <form action="{{ route('logout') }}" method="POST" style="display:contents">
        @csrf
        <button type="submit" class="bottom-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Keluar
        </button>
      </form>
    @else
      {{-- Non-driver: menu lengkap --}}
      <a href="{{ route('dashboard.index') }}"
         class="bottom-item {{ request()->routeIs('dashboard.index') ? 'active' : '' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="{{ route('dashboard.agen.distribusi.index') }}"
         class="bottom-item {{ request()->routeIs('dashboard.agen.distribusi.index') ? 'active' : '' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Realisasi
      </a>
      <a href="{{ route('dashboard.agen.akuntansi.brimola.index') }}"
         class="bottom-item {{ request()->routeIs('dashboard.agen.akuntansi.brimola.*') ? 'active' : '' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        BRImola
      </a>
      <button class="bottom-item" onclick="toggleMobileMore()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        Menu
      </button>
    @endif
  </div>
</nav>

<!-- Mobile more menu — sesuai sidebar non-driver -->
<div id="mobileMoreMenu" class="bottom-more-menu" style="display:none">
  <p style="font-size:11px;color:var(--muted);margin-bottom:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em">Menu Lengkap</p>
  <div class="bottom-more-grid">
    {{-- MAP --}}
    <a href="{{ route('dashboard.nik.list') }}" class="bottom-more-item {{ request()->routeIs('dashboard.nik.*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Monitor NIK
    </a>
    <a href="{{ route('dashboard.akun.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.akun.*','dashboard.batch.*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      Scrape Data
    </a>
    {{-- Operasional --}}
    <a href="{{ route('dashboard.agen.operasional.kitir.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.operasional.kitir*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
      Kitir
    </a>
    <a href="{{ route('dashboard.agen.operasional.sj.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.operasional.sj*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Surat Jalan
    </a>
    {{-- Distribusi --}}
    <a href="{{ route('dashboard.agen.distribusi.gudang.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.distribusi.gudang*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="9" y="12" width="6" height="9"/></svg>
      Gudang
    </a>
    <a href="{{ route('dashboard.agen.distribusi.stok') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.distribusi.stok') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      Stok Armada
    </a>
    {{-- Akuntansi --}}
    <a href="{{ route('dashboard.agen.akuntansi.tebusan.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.akuntansi.tebusan*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Tebusan
    </a>
    <a href="{{ route('dashboard.agen.akuntansi.brimola.audit.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.akuntansi.brimola.audit*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11H6a3 3 0 0 0-3 3v3a3 3 0 0 0 3 3h3"/><path d="M15 13l2 2 4-4"/><circle cx="12" cy="6" r="4"/></svg>
      Audit Bayar
    </a>
    <a href="{{ route('dashboard.agen.akuntansi.kas.index') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.akuntansi.kas*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      Kas Kecil
    </a>
    {{-- Sistem --}}
    <a href="{{ route('dashboard.token.input') }}" class="bottom-more-item {{ request()->routeIs('dashboard.token.*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Token
    </a>
    <a href="{{ route('dashboard.agen.db.pangkalan') }}" class="bottom-more-item {{ request()->routeIs('dashboard.agen.db.pangkalan*') ? 'active' : '' }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      Database
    </a>
  </div>
  <button onclick="toggleMobileMore()" style="width:100%;margin-top:12px;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--muted);font-size:13px;cursor:pointer">
    Tutup
  </button>
</div>

@stack('scripts')

<script>
// ── Theme ─────────────────────────────────────────────
const savedTheme = (() => {
  const t = localStorage.getItem('lpg-theme') || 'cerah';
  return t === 'normal' ? 'cerah' : t; // migrasi tema lama
})();
document.documentElement.setAttribute('data-theme', savedTheme);
document.querySelectorAll('.theme-pill').forEach(p => {
  p.classList.toggle('active', p.dataset.t === savedTheme);
  p.addEventListener('click', () => {
    const t = p.dataset.t;
    document.documentElement.setAttribute('data-theme', t);
    localStorage.setItem('lpg-theme', t);
    document.querySelectorAll('.theme-pill').forEach(x => x.classList.toggle('active', x.dataset.t === t));
  });
});
// Sync system dark mode secara real-time jika tema = "system"
if (savedTheme === 'system') {
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    // CSS sudah handle via @media, tidak perlu JS — tapi force repaint
    document.documentElement.setAttribute('data-theme', 'system');
  });
}

// ── Sidebar collapse ─────────────────────────────────
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const collapsed = s.classList.toggle('collapsed');
  localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
}
// Restore state
(function() {
  const s = document.getElementById('sidebar');
  if (s && localStorage.getItem('sidebar-collapsed') === '1') {
    s.classList.add('collapsed');
  }
})();

// ── Mobile more menu ──────────────────────────────────
function toggleMobileMore() {
  const m = document.getElementById('mobileMoreMenu');
  m.style.display = m.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
  const m = document.getElementById('mobileMoreMenu');
  if (!m.contains(e.target) && !e.target.closest('[onclick="toggleMobileMore()"]')) {
    m.style.display = 'none';
  }
});

// ── Batch status polling ──────────────────────────────
// Hanya poll saat ada indikator batch running di halaman
let batchPollInterval = null;

function pollBatch() {
  fetch('{{ route("dashboard.batch.status-api") }}')
    .then(r => r.json())
    .then(d => {
      const ind = document.getElementById('batchIndicator');
      if (d.running) {
        ind?.classList.remove('hidden');
        if (d.progress?.label) {
          const lbl = document.getElementById('batchLabel');
          if (lbl) lbl.textContent = `[${d.progress.current}/${d.progress.total}] ${d.progress.label}`;
          const sp = document.getElementById('sidebarProgressText');
          if (sp) sp.textContent = `${d.progress.current}/${d.progress.total} — ${d.progress.label}`;
        }
      } else {
        ind?.classList.add('hidden');
        // Batch selesai — stop polling
        if (batchPollInterval) {
          clearInterval(batchPollInterval);
          batchPollInterval = null;
        }
      }
    }).catch(()=>{});
}

// Hanya mulai polling jika batch indicator terlihat (sedang running)
if (document.getElementById('batchIndicator') && !document.getElementById('batchIndicator').classList.contains('hidden')) {
  batchPollInterval = setInterval(pollBatch, 8000); // 8 detik, cukup
}

// ── Stop batch via fetch (bukan redirect) ─────────────
document.getElementById('stopFormNav')?.addEventListener('submit', async e => {
  e.preventDefault();
  const res  = await fetch('{{ route("dashboard.batch.stop") }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
  });
  const data = await res.json();
  if (data.success) document.getElementById('batchIndicator')?.classList.add('hidden');
});

// ── Dropdown touch support ────────────────────────────
document.querySelectorAll('.dropdown').forEach(d => {
  d.addEventListener('click', e => {
    if (window.innerWidth <= 768) {
      d.classList.toggle('open');
      e.stopPropagation();
    }
  });
});

// ── Notifikasi polling ─────────────────────────────────────────
let notifOpen = false;
async function pollNotifCount() {
  try {
    const r = await fetch('{{ route("dashboard.notifikasi.count") }}');
    if (!r.ok) return;
    const d = await r.json();
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (d.count > 0) {
      badge.textContent = d.count > 99 ? '99+' : d.count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  } catch(e) {}
}

async function toggleNotifDropdown() {
  const dd = document.getElementById('notif-dropdown');
  notifOpen = !notifOpen;
  dd.style.display = notifOpen ? 'block' : 'none';
  if (notifOpen) loadNotifs();
}

async function loadNotifs() {
  const list = document.getElementById('notif-list');
  if (!list) return;
  try {
    const r = await fetch('{{ route("dashboard.notifikasi.terbaru") }}');
    const d = await r.json();
    if (!d.notifs || d.notifs.length === 0) {
      list.innerHTML = '<div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">Tidak ada notifikasi</div>';
      return;
    }
    list.innerHTML = d.notifs.map(n => `
      <a href="${n.url || '#'}" onclick="bacaNotif(${n.id}, event)"
         style="display:flex;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);
                text-decoration:none;background:${n.is_read ? 'transparent' : 'rgba(14,165,233,.05)'}">
        <div style="flex-shrink:0;width:32px;height:32px;border-radius:8px;
                    background:${n.warna}22;display:flex;align-items:center;
                    justify-content:center;font-size:15px">
          ${n.icon}
        </div>
        <div style="flex:1;min-width:0">
          <p style="font-size:12px;font-weight:${n.is_read?'400':'600'};color:var(--text);
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${n.judul}</p>
          <p style="font-size:11px;color:var(--muted);margin-top:1px;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${n.pesan}</p>
          <p style="font-size:10px;color:var(--muted);margin-top:2px">${n.waktu}</p>
        </div>
        ${!n.is_read ? '<div style="width:7px;height:7px;border-radius:50%;background:#0EA5E9;flex-shrink:0;margin-top:5px"></div>' : ''}
      </a>`).join('');
  } catch(e) {
    list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--muted);font-size:12px">Gagal memuat</div>';
  }
}

async function bacaNotif(id, e) {
  if (e) e.preventDefault();
  try {
    await fetch(`/dashboard/notifikasi/${id}/baca`, {
      method:'POST',
      headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}
    });
  } catch(err) {}
  pollNotifCount();
}

async function bacaSemuaNotif() {
  try {
    await fetch('{{ route("dashboard.notifikasi.baca-semua") }}', {
      method:'POST',
      headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}
    });
  } catch(err) {}
  pollNotifCount();
  loadNotifs();
}

document.addEventListener('click', e => {
  if (!notifOpen) return;
  if (!e.target.closest('#btn-notif') && !e.target.closest('#notif-dropdown')) {
    const dd = document.getElementById('notif-dropdown');
    if (dd) dd.style.display = 'none';
    notifOpen = false;
  }
});

@auth pollNotifCount(); setInterval(pollNotifCount, 30000); @endauth
</script>
</body>
</html>