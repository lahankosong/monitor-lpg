<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#151F28">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>@yield('title', 'Distribusi') — LPG Monitor</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

    * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

    :root {
      --clr: #151F28;
      --accent: #29fd53;
      --accent-dark: #1ed644;
      --surface: #1E2A36;
      --card: #253341;
      --border: rgba(255,255,255,.08);
      --text: #F0F4F8;
      --muted: #8899A6;
      --danger: #FF4D4F;
      --warning: #FAAD14;
      --info: #1890FF;
      --nav-h: 80px;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--clr);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: calc(var(--nav-h) + 16px);
      overscroll-behavior: none;
    }

    /* ── TOP BAR ─────────────────────────────────────────── */
    .topbar {
      background: var(--surface);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      border-bottom: 1px solid var(--border);
    }
    .topbar-brand { display:flex; align-items:center; gap:10px; }
    .topbar-logo {
      width: 32px; height: 32px; background: var(--accent);
      border-radius: 8px; display:flex; align-items:center; justify-content:center;
    }
    .topbar-logo svg { width:18px; height:18px; }
    .topbar-title { font-size:15px; font-weight:600; color:var(--text); line-height:1.2; }
    .topbar-sub   { font-size:11px; color:var(--muted); }
    .topbar-user  { text-align:right; }
    .topbar-user .nama  { font-size:12px; font-weight:500; color:var(--text); }
    .topbar-user .agen  { font-size:10px; color:var(--muted); }

    /* ── PAGE CONTENT ─────────────────────────────────────── */
    .page-content { padding: 12px; }

    /* ── CARDS ────────────────────────────────────────────── */
    .card {
      background: var(--card);
      border-radius: 14px;
      border: 1px solid var(--border);
      overflow: hidden;
      margin-bottom: 12px;
    }
    .card-header {
      padding: 12px 14px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* ── PANGKALAN ROW ────────────────────────────────────── */
    .pkln-row {
      display: flex;
      align-items: center;
      padding: 12px 14px;
      border-bottom: 1px solid var(--border);
      gap: 10px;
      transition: background .15s;
    }
    .pkln-row:last-child { border-bottom: none; }
    .pkln-row:active { background: rgba(255,255,255,.03); }

    .pkln-urut {
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--accent); color: var(--clr);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; flex-shrink: 0;
    }
    .pkln-urut.done   { background: rgba(41,253,83,.2); color: var(--accent); }
    .pkln-urut.partial{ background: rgba(250,173,20,.2); color: var(--warning); }
    .pkln-urut.alih   { background: rgba(24,144,255,.2); color: var(--info); }

    .pkln-info { flex:1; min-width:0; }
    .pkln-nama { font-size:14px; font-weight:600; color:var(--text);
                 white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pkln-sub  { font-size:11px; color:var(--muted); margin-top:1px; }
    .pkln-tambahan { font-size:10px; color:var(--info); font-weight:600; margin-top:2px; }

    .pkln-right { flex-shrink:0; text-align:right; }
    .pkln-qty   { font-size:18px; font-weight:700; color:var(--text); line-height:1; }
    .pkln-qty.done    { color: var(--accent); }
    .pkln-qty.partial { color: var(--warning); }
    .pkln-qty-label   { font-size:10px; color:var(--muted); margin-top:2px; }

    /* ── BADGES ───────────────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center;
      padding: 2px 8px; border-radius: 99px;
      font-size: 11px; font-weight: 600;
    }
    .badge-pending { background:rgba(250,173,20,.15); color:var(--warning); }
    .badge-done    { background:rgba(41,253,83,.15);  color:var(--accent); }
    .badge-partial { background:rgba(24,144,255,.15); color:var(--info); }
    .badge-batal   { background:rgba(255,77,79,.15);  color:var(--danger); }
    .badge-alih    { background:rgba(24,144,255,.15); color:var(--info); }

    /* ── BUTTONS ──────────────────────────────────────────── */
    .btn {
      display: inline-flex; align-items: center; justify-content: center;
      border: none; border-radius: 10px; font-family: 'Poppins', sans-serif;
      font-size: 13px; font-weight: 600; cursor: pointer;
      transition: all .15s; padding: 9px 16px;
    }
    .btn:active { transform: scale(.97); }
    .btn-accent  { background: var(--accent); color: var(--clr); }
    .btn-outline { background: none; border: 1px solid var(--border); color: var(--text); }
    .btn-danger  { background: var(--danger); color: #fff; }
    .btn-full    { width: 100%; }
    .btn-lg      { padding: 13px; font-size: 15px; border-radius: 12px; }

    /* ── FORM INPUTS ──────────────────────────────────────── */
    .finput {
      width: 100%;
      background: rgba(255,255,255,.06);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 15px;
      font-family: 'Poppins', sans-serif;
      color: var(--text);
      outline: none;
      transition: border-color .2s;
      -webkit-appearance: none;
    }
    .finput:focus { border-color: var(--accent); }
    .finput::placeholder { color: var(--muted); }
    .flabel {
      display: block;
      font-size: 11px; font-weight: 600;
      color: var(--muted);
      text-transform: uppercase; letter-spacing: .06em;
      margin-bottom: 6px;
    }

    /* ── QTY INPUT BESAR ──────────────────────────────────── */
    .qty-wrap { display:flex; align-items:center; gap:12px; }
    .qty-btn {
      width: 44px; height: 44px;
      background: rgba(255,255,255,.06);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      color: var(--text); font-size: 22px; font-weight: 300;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; flex-shrink: 0; transition: all .15s;
    }
    .qty-btn:active { background: rgba(255,255,255,.12); transform: scale(.95); }
    .qty-input-big {
      flex: 1; text-align: center;
      font-size: 36px; font-weight: 700;
      background: rgba(255,255,255,.06);
      border: 1.5px solid var(--border);
      border-radius: 12px; padding: 10px;
      color: var(--text); font-family: 'Poppins', sans-serif;
      outline: none; transition: border-color .2s;
    }
    .qty-input-big:focus { border-color: var(--accent); }
    .qty-input-big.warn  { border-color: var(--danger); color: var(--danger); }

    /* ── PILL / QUICK SELECT ──────────────────────────────── */
    .pill-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
    .pill {
      padding: 6px 14px; border-radius: 99px;
      border: 1.5px solid var(--border);
      font-size: 12px; font-weight: 500;
      cursor: pointer; color: var(--muted);
      background: transparent; font-family: 'Poppins', sans-serif;
      transition: all .15s;
    }
    .pill.active, .pill:active {
      background: var(--accent); border-color: var(--accent);
      color: var(--clr);
    }

    /* ── SISA SECTION ─────────────────────────────────────── */
    .sisa-section {
      border: 1.5px solid rgba(250,173,20,.3);
      border-radius: 12px;
      background: rgba(250,173,20,.05);
      padding: 12px;
      margin-bottom: 14px;
    }
    .sisa-option {
      display: flex; align-items: center; gap:10px;
      padding: 10px; border-radius: 8px;
      border: 1.5px solid transparent;
      cursor: pointer; margin-bottom: 6px;
      transition: border-color .15s;
    }
    .sisa-option:last-child { margin-bottom: 0; }
    .sisa-option input[type=checkbox] {
      width: 18px; height: 18px; cursor: pointer;
      accent-color: var(--accent); flex-shrink: 0;
    }
    .sisa-option.checked { border-color: var(--accent); background: rgba(41,253,83,.05); }
    .sisa-option-label { font-size: 13px; font-weight: 600; color: var(--text); }
    .sisa-option-desc  { font-size: 11px; color: var(--muted); margin-top:1px; }

    /* ── SHEET MODAL (BOTTOM SHEET) ───────────────────────── */
    .sheet-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.6); z-index: 200;
      align-items: flex-end;
    }
    .sheet-overlay.open { display: flex; }
    .sheet {
      background: var(--surface);
      border-radius: 20px 20px 0 0;
      width: 100%; padding: 0;
      max-height: 94vh; overflow-y: auto;
      animation: slideUp .25s cubic-bezier(.32,.72,0,1);
    }
    @keyframes slideUp {
      from { transform: translateY(100%); }
      to   { transform: translateY(0); }
    }
    .sheet-handle {
      width: 40px; height: 4px;
      background: rgba(255,255,255,.2);
      border-radius: 2px; margin: 10px auto 0;
    }
    .sheet-header {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
      display: flex; justify-content: space-between; align-items: center;
    }
    .sheet-title { font-size: 16px; font-weight: 700; color: var(--text); }
    .sheet-sub   { font-size: 12px; color: var(--muted); margin-top:2px; }
    .sheet-close {
      background: rgba(255,255,255,.08); border: none;
      width: 28px; height: 28px; border-radius: 50%;
      color: var(--muted); font-size: 16px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
    }
    .sheet-body { padding: 16px 20px 24px; }

    /* ── INFO CARDS 3 KOLOM ───────────────────────────────── */
    .info-grid {
      display: grid; grid-template-columns: 1fr 1fr 1fr;
      gap: 8px; margin-bottom: 16px;
    }
    .info-card {
      background: rgba(255,255,255,.05);
      border-radius: 10px; padding: 10px; text-align: center;
    }
    .info-card-label { font-size: 9px; color: var(--muted); font-weight: 600;
                       text-transform: uppercase; letter-spacing: .06em; margin-bottom:4px; }
    .info-card-val   { font-size: 24px; font-weight: 700; line-height: 1; }

    /* ── WARN BOX ─────────────────────────────────────────── */
    .warn-box {
      display: none;
      background: rgba(255,77,79,.1);
      border: 1px solid rgba(255,77,79,.3);
      border-radius: 8px; padding: 10px 12px;
      font-size: 12px; color: var(--danger);
      font-weight: 500; line-height: 1.5;
      margin-bottom: 12px;
    }
    .info-box {
      display: none;
      background: rgba(24,144,255,.1);
      border: 1px solid rgba(24,144,255,.3);
      border-radius: 8px; padding: 10px 12px;
      font-size: 12px; color: var(--info);
      font-weight: 500; margin-bottom: 12px;
    }

    /* ── PENGALIHAN MULTI-BARIS ───────────────────────────── */
    .alih-row {
      display: grid; grid-template-columns: 1fr 60px 28px;
      gap: 6px; align-items: center; margin-bottom: 6px;
    }
    .alih-row select, .alih-row input {
      background: rgba(255,255,255,.06);
      border: 1.5px solid var(--border);
      border-radius: 8px; padding: 8px 10px;
      font-size: 12px; color: var(--text);
      font-family: 'Poppins', sans-serif; outline: none; width: 100%;
    }
    .alih-row input { text-align: center; font-weight: 700; font-size: 13px; }
    .btn-del-alih {
      background: none; border: 1px solid rgba(255,77,79,.3);
      color: var(--danger); border-radius: 6px;
      width: 28px; height: 28px; cursor: pointer; font-size: 14px;
      display: flex; align-items: center; justify-content: center;
    }

    /* ── PROGRESS BAR ─────────────────────────────────────── */
    .progress-bar {
      height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;
    }
    .progress-fill {
      height: 100%; border-radius: 2px;
      background: var(--accent); transition: width .3s;
    }

    /* ─── MAGIC NAVIGATION ─────────────────────────────────── */
    .navigation {
      position: fixed; bottom: 12px; left: 50%;
      transform: translateX(-50%);
      z-index: 150;
      width: calc(100% - 24px); max-width: 400px;
      height: 70px;
      background: var(--surface);
      border-radius: 14px;
      border: 1px solid var(--border);
      display: flex;
      justify-content: center;
      align-items: center;
      box-shadow: 0 8px 32px rgba(0,0,0,.4);
    }
    .navigation ul {
      display: flex;
      width: 100%;
      padding: 0 10px;
      list-style: none;
    }
    .navigation ul li {
      position: relative;
      list-style: none;
      flex: 1;
      height: 70px;
      z-index: 1;
    }
    .navigation ul li a {
      position: relative;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      width: 100%;
      height: 100%;
      text-decoration: none;
    }
    .navigation ul li a .nav-icon {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 28px; height: 28px;
      transition: transform .4s cubic-bezier(.32,.72,0,1);
      color: var(--muted);
      font-size: 22px;
    }
    .navigation ul li.active a .nav-icon {
      transform: translateY(-28px);
      color: var(--clr);
    }
    .navigation ul li a .nav-text {
      position: absolute;
      bottom: 6px;
      color: var(--muted);
      font-weight: 500;
      font-size: 10px;
      letter-spacing: .04em;
      transition: all .4s cubic-bezier(.32,.72,0,1);
      opacity: 0;
      transform: translateY(8px);
    }
    .navigation ul li.active a .nav-text {
      opacity: 1;
      transform: translateY(0);
      color: var(--clr);
    }

    /* Indicator bubble */
    .nav-indicator {
      position: absolute;
      top: -32px;
      width: 54px; height: 54px;
      background: var(--accent);
      border-radius: 50%;
      border: 5px solid var(--clr);
      transition: transform .4s cubic-bezier(.32,.72,0,1);
      pointer-events: none;
    }
    .nav-indicator::before,
    .nav-indicator::after {
      content: '';
      position: absolute;
      top: 50%; width: 18px; height: 18px;
      background: transparent;
    }
    .nav-indicator::before {
      left: -19px;
      border-top-right-radius: 18px;
      box-shadow: 1px -8px 0 0 var(--clr);
    }
    .nav-indicator::after {
      right: -19px;
      border-top-left-radius: 18px;
      box-shadow: -1px -8px 0 0 var(--clr);
    }

    /* Posisi indicator per item (5 item) */
    .navigation ul li:nth-child(1).active ~ .nav-indicator { transform: translateX(calc((100%/5 * 0) + 0px)); }
    .navigation ul li:nth-child(2).active ~ .nav-indicator { transform: translateX(calc(72px * 1)); }
    .navigation ul li:nth-child(3).active ~ .nav-indicator { transform: translateX(calc(72px * 2)); }
    .navigation ul li:nth-child(4).active ~ .nav-indicator { transform: translateX(calc(72px * 3)); }
    .navigation ul li:nth-child(5).active ~ .nav-indicator { transform: translateX(calc(72px * 4)); }

    /* Success flash */
    .flash-success {
      position: fixed; top: 70px; left: 12px; right: 12px; z-index: 500;
      background: rgba(41,253,83,.15); border: 1px solid var(--accent);
      border-radius: 10px; padding: 10px 14px;
      font-size: 13px; color: var(--accent); font-weight: 500;
      animation: flashIn .3s ease; display: none;
    }
    @keyframes flashIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:none; } }

    /* Scrollbar minimal */
    ::-webkit-scrollbar { width: 3px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
  </style>
</head>
<body>

<!-- Flash message -->
@if(session('success'))
<div class="flash-success" id="flash-msg" style="display:block">
  ✓ {{ session('success') }}
</div>
@endif

<!-- Top bar -->
<div class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="#151F28" stroke-width="2.5" stroke-linecap="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
    </div>
    <div>
      <div class="topbar-title">@yield('topbar-title', 'Distribusi')</div>
      <div class="topbar-sub">@yield('topbar-sub', now()->translatedFormat('l, d F Y'))</div>
    </div>
  </div>
  <div class="topbar-user">
    @php $agen = \App\Models\Agen::profil(); @endphp
    <div class="nama">{{ auth()->user()?->name ?? '—' }}</div>
    <div class="agen">{{ $agen?->nama_agen ?? '' }}</div>
  </div>
</div>

<!-- Page content -->
<div class="page-content">
  @yield('content')
</div>

<!-- Magic Navigation -->
<div class="navigation">
  <ul>
    <li class="list {{ request()->routeIs('dashboard.agen.driver.index') ? 'active' : '' }}">
      <a href="{{ route('dashboard.agen.driver.index') }}">
        <span class="nav-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/>
            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
        </span>
        <span class="nav-text">Distribusi</span>
      </a>
    </li>
    <li class="list {{ request()->routeIs('dashboard.agen.driver.stok') ? 'active' : '' }}">
      <a href="{{ route('dashboard.agen.driver.stok') }}">
        <span class="nav-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
          </svg>
        </span>
        <span class="nav-text">Stok</span>
      </a>
    </li>
    <li class="list {{ request()->routeIs('dashboard.agen.driver.histori') ? 'active' : '' }}">
      <a href="{{ route('dashboard.agen.driver.histori') }}">
        <span class="nav-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </span>
        <span class="nav-text">Histori</span>
      </a>
    </li>
    <li class="list">
      <a href="{{ route('dashboard.index') }}">
        <span class="nav-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          </svg>
        </span>
        <span class="nav-text">Admin</span>
      </a>
    </li>
    <div class="nav-indicator"></div>
  </ul>
</div>

<script>
// Magic Navigation — active state + indicator
const navList = document.querySelectorAll('.navigation ul .list');
navList.forEach(item => {
  item.addEventListener('click', function() {
    navList.forEach(i => i.classList.remove('active'));
    this.classList.add('active');
  });
});

// Fix indicator position berdasarkan lebar aktual
function fixIndicator() {
  const nav  = document.querySelector('.navigation ul');
  const items = nav.querySelectorAll('.list');
  const ind   = nav.querySelector('.nav-indicator');
  const active = nav.querySelector('.list.active');
  if (!active || !ind) return;
  const idx   = Array.from(items).indexOf(active);
  const w     = nav.offsetWidth / items.length;
  ind.style.transform = `translateX(${w * idx + (w/2 - 27)}px)`;
}
window.addEventListener('resize', fixIndicator);
document.addEventListener('DOMContentLoaded', fixIndicator);
setTimeout(fixIndicator, 100);

// Flash auto-hide
const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => flash.style.display = 'none', 3000);

// Swipe down sheet to close
let touchStartY = 0;
document.addEventListener('touchstart', e => { touchStartY = e.touches[0].clientY; });
document.addEventListener('touchend', e => {
  const dy = e.changedTouches[0].clientY - touchStartY;
  if (dy > 70) {
    document.querySelectorAll('.sheet-overlay.open').forEach(el => {
      el.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});

function bukaSheet(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function tutupSheet(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
</script>

@stack('scripts')
</body>
</html>
