@extends('layouts.app')
@section('title', 'Edit Akun Pangkalan')

@section('content')

<div style="max-width:520px;margin:0 auto">
  <div style="margin-bottom:16px">
    <a href="{{ route('dashboard.akun.index') }}"
       style="font-size:12px;color:var(--muted);text-decoration:none">← Kembali ke daftar akun</a>
  </div>

  <div class="card" style="padding:24px">
    <h1 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">Edit Akun Pangkalan</h1>
    <p style="font-size:12px;color:var(--muted);margin-bottom:20px">
      Kosongkan kolom password jika tidak ingin mengubah.
    </p>

    @if($errors->any())
    <div style="background:#FEE2E2;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#991B1B">
      @foreach($errors->all() as $e) ✗ {{ $e }}<br> @endforeach
    </div>
    @endif

    <form action="{{ route('dashboard.akun.update', $akun->id) }}" method="POST">
      @csrf @method('PUT')

      <div style="display:flex;flex-direction:column;gap:14px">

        <div>
          <label class="flabel">Nama Pangkalan *</label>
          <input name="label" required value="{{ old('label', $akun->label) }}" class="finput">
        </div>

        <div>
          <label class="flabel">Email / No HP *</label>
          <input name="username" required type="text"
                 value="{{ old('username', $akun->username) }}"
                 placeholder="email@gmail.com atau 081234567890"
                 class="finput">
        </div>

        <div>
          <label class="flabel">
            Password / PIN
            <span style="font-weight:400;color:var(--muted)">(kosongkan jika tidak diubah)</span>
          </label>
          <div style="display:flex;gap:8px">
            <input name="password" type="password" id="passwordInput"
                   class="finput" placeholder="••••••••" style="flex:1">
            <button type="button" onclick="lihatPassword({{ $akun->id }})"
                    style="border:1px solid var(--border);background:var(--surface);color:var(--muted);
                           border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;
                           white-space:nowrap;transition:all .15s"
                    onmouseover="this.style.color='var(--text)'"
                    onmouseout="this.style.color='var(--muted)'">
              Lihat
            </button>
          </div>
          <p id="passwordInfo" style="font-size:11px;color:var(--muted);margin-top:4px;display:none"></p>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" id="is_active" value="1"
                 {{ $akun->is_active ? 'checked' : '' }}
                 style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
          <label for="is_active" style="font-size:13px;color:var(--text);cursor:pointer">
            Akun aktif (ikut dalam batch scraping)
          </label>
        </div>

        @if($akun->registration_id)
        <div style="background:var(--bg);border-radius:8px;padding:10px 14px;font-size:11px;color:var(--muted)">
          <p>Registration ID: <span style="font-family:monospace;color:var(--text)">{{ $akun->registration_id }}</span></p>
          <p style="margin-top:3px">Pangkalan ID: <span style="font-family:monospace">{{ $akun->pangkalan_id }}</span></p>
        </div>
        @endif
      </div>

      <div style="display:flex;gap:8px;margin-top:20px">
        <button type="submit"
                style="background:var(--accent);color:#151F28;border:none;border-radius:8px;
                       padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer">
          Simpan Perubahan
        </button>
        <a href="{{ route('dashboard.akun.index') }}"
           style="border:1px solid var(--border);background:var(--surface);color:var(--text);
                  border-radius:8px;padding:9px 16px;font-size:13px;text-decoration:none">
          Batal
        </a>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
async function lihatPassword(id) {
  const res  = await fetch(`/dashboard/akun/${id}/password`, {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
      'Accept': 'application/json'
    }
  });
  const data = await res.json();
  const info  = document.getElementById('passwordInfo');
  const input = document.getElementById('passwordInput');

  if (data.success) {
    input.value = data.password;
    input.type  = 'text';
    info.textContent = '⚠ Password ditampilkan — auto-sembunyikan 8 detik';
    info.style.color = '#F59E0B';
    info.style.display = 'block';
    setTimeout(() => {
      input.type = 'password';
      info.style.display = 'none';
    }, 8000);
  } else {
    info.textContent = 'Password tidak tersedia — perlu diisi ulang';
    info.style.color = '#DC2626';
    info.style.display = 'block';
  }
}
</script>
@endpush
@endsection
