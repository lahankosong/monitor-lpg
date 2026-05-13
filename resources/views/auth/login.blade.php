<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — LPG Monitor</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:      #0F1923;
      --surface: #1A2535;
      --card:    #1E2D3D;
      --border:  rgba(255,255,255,.08);
      --accent:  #29fd53;
      --text:    #F0F4F8;
      --muted:   #8899A6;
      --danger:  #FF4D4F;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .wrap {
      width: 100%;
      max-width: 420px;
    }
    .brand {
      text-align: center;
      margin-bottom: 32px;
    }
    .brand-logo {
      width: 56px; height: 56px;
      background: var(--accent);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 14px;
    }
    .brand-logo svg { width: 28px; height: 28px; }
    .brand-title { font-size: 22px; font-weight: 700; color: var(--text); }
    .brand-sub   { font-size: 13px; color: var(--muted); margin-top: 4px; }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px;
    }
    .card-title {
      font-size: 18px; font-weight: 600; color: var(--text);
      margin-bottom: 24px;
    }

    .field { margin-bottom: 18px; }
    .flabel {
      display: block;
      font-size: 11px; font-weight: 600; color: var(--muted);
      text-transform: uppercase; letter-spacing: .06em;
      margin-bottom: 6px;
    }
    .finput {
      width: 100%;
      background: rgba(255,255,255,.05);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 14px;
      font-family: 'Poppins', sans-serif;
      color: var(--text);
      outline: none;
      transition: border-color .2s;
    }
    .finput:focus { border-color: var(--accent); }
    .finput::placeholder { color: var(--muted); }
    .finput.error { border-color: var(--danger); }

    .err-msg {
      font-size: 12px; color: var(--danger);
      margin-top: 5px;
    }

    .remember {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 20px; cursor: pointer;
    }
    .remember input { accent-color: var(--accent); width: 15px; height: 15px; cursor: pointer; }
    .remember span  { font-size: 13px; color: var(--muted); }

    .btn-login {
      width: 100%;
      background: var(--accent);
      color: #0F1923;
      border: none;
      border-radius: 10px;
      padding: 13px;
      font-size: 15px;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      cursor: pointer;
      transition: opacity .15s;
      letter-spacing: .02em;
    }
    .btn-login:hover   { opacity: .9; }
    .btn-login:active  { opacity: .8; transform: scale(.99); }
    .btn-login:disabled{ opacity: .5; cursor: not-allowed; }

    .roles-info {
      margin-top: 24px;
      border-top: 1px solid var(--border);
      padding-top: 20px;
    }
    .roles-title { font-size: 11px; color: var(--muted); margin-bottom: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .role-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 12px;
    }
    .role-row:last-child { border-bottom: none; }
    .role-badge {
      font-size: 10px; font-weight: 600; padding: 2px 8px;
      border-radius: 99px;
    }
    .badge-dir  { background:rgba(255,77,79,.15); color:#FF4D4F; }
    .badge-mgr  { background:rgba(24,144,255,.15); color:#1890FF; }
    .badge-adm  { background:rgba(41,253,83,.15);  color:#29fd53; }
    .badge-drv  { background:rgba(250,173,20,.15); color:#FAAD14; }

    .version { text-align: center; margin-top: 20px; font-size: 11px; color: var(--muted); }
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0F1923" stroke-width="2.5" stroke-linecap="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
    </div>
    <div class="brand-title">LPG Monitor</div>
    <div class="brand-sub">Sistem Monitoring Distribusi LPG Subsidi</div>
  </div>

  <div class="card">
    <p class="card-title">Masuk ke akun Anda</p>

    @if($errors->any())
    <div style="background:rgba(255,77,79,.1);border:1px solid rgba(255,77,79,.3);border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:13px;color:#FF4D4F">
      {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="form-login">
      @csrf

      <div class="field">
        <label class="flabel" for="email">Email</label>
        <input type="email" name="email" id="email" class="finput {{ $errors->has('email') ? 'error' : '' }}"
               value="{{ old('email') }}" placeholder="email@contoh.com"
               autocomplete="email" autofocus required>
      </div>

      <div class="field">
        <label class="flabel" for="password">Password</label>
        <div style="position:relative">
          <input type="password" name="password" id="password" class="finput"
                 placeholder="••••••••" autocomplete="current-password" required
                 style="padding-right:44px">
          <button type="button" onclick="togglePwd()"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:4px">
            <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <label class="remember">
        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
        <span>Ingat saya</span>
      </label>

      <button type="submit" class="btn-login" id="btn-submit">Masuk</button>
    </form>

    {{-- Info role untuk development --}}
    @if(config('app.env') === 'local')
    <div class="roles-info">
      <p class="roles-title">Akun default (development)</p>
      <div class="role-row">
        <span style="color:var(--text)">direktur@lpgmonitor.local</span>
        <span class="role-badge badge-dir">Direktur</span>
      </div>
      <div class="role-row">
        <span style="color:var(--text)">manajer@lpgmonitor.local</span>
        <span class="role-badge badge-mgr">Manajer</span>
      </div>
      <div class="role-row">
        <span style="color:var(--text)">admin@lpgmonitor.local</span>
        <span class="role-badge badge-adm">Admin</span>
      </div>
      <p style="font-size:10px;color:var(--muted);margin-top:8px">Password: [role]123 — hapus blok ini di production</p>
    </div>
    @endif
  </div>

  <p class="version">v1.0 · {{ now()->year }}</p>
</div>

<script>
function togglePwd() {
  const pwd  = document.getElementById('password');
  const icon = document.getElementById('eye-icon');
  const show = pwd.type === 'password';
  pwd.type = show ? 'text' : 'password';
  icon.innerHTML = show
    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
document.getElementById('form-login').addEventListener('submit', function() {
  document.getElementById('btn-submit').disabled = true;
  document.getElementById('btn-submit').textContent = 'Memproses...';
});
</script>
</body>
</html>
