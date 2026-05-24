{{--
  resources/views/partials/distribusi-styles.blade.php
  Include di layouts/app.blade.php atau di tiap halaman distribusi:
  @include('partials.distribusi-styles')
--}}
<style>
/* ── Distribusi UI — Responsive Design System ──────────────────── */

/* Filter bar */
.filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center}
.filter-bar select,
.filter-bar input[type=date],
.filter-bar input[type=text]{
  border:1px solid var(--border);background:var(--surface);color:var(--text);
  border-radius:8px;padding:7px 10px;font-size:13px;outline:none;min-width:100px}

/* Stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:20px}
.stat-card{background:var(--surface);border-radius:10px;padding:14px 16px;border:1px solid var(--border)}
.stat-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px}
.stat-value{font-size:22px;font-weight:700}
.stat-sub{font-size:11px;color:var(--muted);margin-top:3px}

/* Badge */
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600}
.badge-warn{background:#FAEEDA;color:#BA7517}
.badge-purple{background:#EEEDFE;color:#534AB7}
.badge-green{background:#E1F5EE;color:#085041}
.badge-blue{background:#E6F1FB;color:#185FA5}
.badge-danger{background:#FCEBEB;color:#A32D2D}
.badge-muted{background:var(--bg);color:var(--muted)}

/* Section title */
.section-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* Alert banners */
.alert-banner{padding:10px 14px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:14px;display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap}
.alert-warn{background:#FAEEDA;color:#633806;border:1px solid #FAC775}
.alert-danger{background:#FCEBEB;color:#501313;border:1px solid #F7C1C1}
.alert-success{background:#E1F5EE;color:#04342C;border:1px solid #9FE1CB}
.alert-info{background:#E6F1FB;color:#0C447C;border:1px solid #B5D4F4}

/* Armada card grid */
.armada-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-bottom:20px}
.armada-card{background:var(--surface);border-radius:10px;border:1px solid var(--border);overflow:hidden}
.armada-header{padding:12px 16px;background:linear-gradient(135deg,#92400E,#B45309);color:#fff;display:flex;justify-content:space-between;align-items:center}
.armada-polisi{font-size:15px;font-weight:700;font-family:monospace}
.armada-qty{font-size:26px;font-weight:700}
.armada-trip{padding:8px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px}
.armada-action{padding:10px 14px}

/* Card */
.card{background:var(--surface);border-radius:10px;border:1px solid var(--border);overflow:hidden;margin-bottom:16px}
.card-header{padding:12px 16px;background:var(--bg);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.card-header-meta{display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:12px;color:var(--muted)}

/* Progress bar */
.progress-bar{height:3px;background:var(--border)}
.progress-fill{height:3px;background:var(--accent);transition:width .3s}
.progress-fill.done{background:#059669}

/* Responsive table */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.table-wrap table{min-width:480px}
table th{white-space:nowrap}

/* Qty input */
.qty-input{width:76px;border:1.5px solid var(--border);border-radius:6px;padding:6px 8px;font-size:15px;font-weight:700;text-align:center;background:var(--surface);color:var(--text);outline:none;transition:border-color .15s}
.qty-input:focus{border-color:var(--accent)}

/* Sisa section */
.sisa-section{background:var(--bg);border-top:1px solid var(--border);padding:14px 16px}
.sisa-label{display:flex;align-items:center;gap:10px;padding:6px 0;flex-wrap:wrap;font-size:13px;font-weight:600}
.sisa-footer{display:flex;justify-content:space-between;align-items:center;margin-top:10px;flex-wrap:wrap;gap:8px}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:16px;width:100%;max-width:400px;overflow:hidden}
.modal-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:15px;font-weight:600}
.modal-close{background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer;line-height:1;padding:0}
.modal-body{padding:18px}
.qty-display{background:var(--bg);border-radius:8px;padding:12px;text-align:center;margin-bottom:14px}
.qty-display-value{font-size:30px;font-weight:700;color:#7C3AED}
.qty-display-label{font-size:11px;color:var(--muted)}
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.form-select{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 10px;font-size:13px;outline:none}
.form-input-lg{width:100%;border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:22px;font-weight:700;text-align:center;background:var(--surface);color:var(--text);outline:none;box-sizing:border-box}
.form-input-lg:focus{border-color:var(--accent)}

/* Tab bar */
.tab-bar{display:flex;border-bottom:1px solid var(--border);margin-bottom:16px;overflow-x:auto}
.tab-btn{background:none;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:13px;font-weight:500;color:var(--muted);cursor:pointer;white-space:nowrap;flex-shrink:0}
.tab-btn.active{border-bottom-color:var(--accent);color:var(--accent);font-weight:600}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s;white-space:nowrap}
.btn:hover{opacity:.85}
.btn-primary{background:var(--accent);color:#fff}
.btn-outline{background:var(--surface);color:var(--text);border:1px solid var(--border)}
.btn-warn{background:#D97706;color:#fff}
.btn-purple{background:#7C3AED;color:#fff}
.btn-danger{background:#DC2626;color:#fff}
.btn-ghost{background:none;border:1px solid #059669;color:#059669}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-block{width:100%;justify-content:center}
.btn-group{display:flex;gap:8px;flex-wrap:wrap}

/* Page header */
.page-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.page-title{font-size:20px;font-weight:700;color:var(--text)}
.page-sub{font-size:12px;color:var(--muted);margin-top:3px}

/* ── Responsive breakpoints ──────────────────────────────────── */
@media(max-width:640px){
  /* Stat grid 2 kolom di mobile */
  .stat-grid{grid-template-columns:1fr 1fr}

  /* Armada card full width */
  .armada-grid{grid-template-columns:1fr}

  /* Filter bar vertikal */
  .filter-bar{flex-direction:column;align-items:stretch}
  .filter-bar select,
  .filter-bar input[type=date],
  .filter-bar input[type=text]{width:100%;min-width:unset}
  .filter-bar .btn{width:100%;justify-content:center}

  /* Page header stack */
  .page-header{flex-direction:column}
  .btn-group{width:100%}
  .btn-group .btn{flex:1;justify-content:center}

  /* Sisa section stack */
  .sisa-footer{flex-direction:column;align-items:stretch}
  .sisa-footer .btn{width:100%;justify-content:center}

  /* Table padding lebih kecil */
  table th,table td{padding:8px 10px}

  /* Alert wrap rapi */
  .alert-banner{flex-direction:column}

  /* Card header stack */
  .card-header{flex-direction:column;align-items:flex-start}
  .card-header-meta{flex-wrap:wrap}

  /* Section title wrap */
  .section-title{flex-wrap:wrap}
}

@media(max-width:400px){
  /* Stat grid 1 kolom di layar sangat kecil */
  .stat-grid{grid-template-columns:1fr}
  .page-title{font-size:17px}
}
</style>
