@extends('layouts.app')
@section('title', 'Edit Akun Pangkalan')

@section('content')

<div class="max-w-lg mx-auto">
  <div class="mb-5">
    <a href="{{ route('dashboard.akun.index') }}"
       class="text-xs text-gray-400 hover:text-gray-700">← Kembali ke daftar akun</a>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <h1 class="font-semibold text-base mb-1">Edit Akun Pangkalan</h1>
    <p class="text-xs text-gray-400 mb-5">
      Kosongkan kolom password jika tidak ingin mengubah password.
    </p>

    <form action="{{ route('dashboard.akun.update', $akun->id) }}" method="POST">
      @csrf @method('PUT')

      <div class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Nama Pangkalan *</label>
          <input name="label" required value="{{ old('label', $akun->label) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                        focus:outline-none focus:border-blue-500
                        @error('label') border-red-400 @enderror">
          @error('label')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Email / No HP *</label>
          <input name="username" required type="text"
                 value="{{ old('username', $akun->username) }}"
                 placeholder="email@gmail.com atau 081234567890"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                        focus:outline-none focus:border-blue-500
                        @error('username') border-red-400 @enderror">
          @error('username')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">
            Password / PIN
            <span class="text-gray-400 font-normal">(kosongkan jika tidak diubah)</span>
          </label>
          <div class="flex gap-2">
            <input name="password" type="password" id="passwordInput"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm
                          focus:outline-none focus:border-blue-500"
                   placeholder="••••••••">
            <button type="button" onclick="lihatPassword({{ ->id }})"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-xs text-gray-500 hover:bg-gray-50 whitespace-nowrap">
              Lihat
            </button>
          </div>
          <p id="passwordInfo" class="text-xs text-gray-400 mt-1 hidden"></p>
        </div>

        <div class="flex items-center gap-2">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" id="is_active" value="1"
                 {{ $akun->is_active ? 'checked' : '' }}
                 class="rounded border-gray-300 text-blue-600">
          <label for="is_active" class="text-sm text-gray-700">Akun aktif (ikut dalam batch scraping)</label>
        </div>

        @if($akun->registration_id)
        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-500">
          <p>Registration ID: <span class="font-mono font-medium">{{ $akun->registration_id }}</span></p>
          <p class="mt-0.5">Pangkalan ID: <span class="font-mono">{{ $akun->pangkalan_id }}</span></p>
        </div>
        @endif
      </div>

      <div class="flex gap-2 mt-5">
        <button type="submit"
                class="bg-blue-600 text-white rounded-lg px-5 py-2 text-sm hover:bg-blue-700">
          Simpan Perubahan
        </button>
        <a href="{{ route('dashboard.akun.index') }}"
           class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">
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
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
  });
  const data = await res.json();
  const info = document.getElementById('passwordInfo');
  const input = document.getElementById('passwordInput');
  if (data.success) {
    info.textContent = 'Password tersimpan: ' + data.password;
    info.classList.remove('hidden');
    input.value = data.password;
    input.type  = 'text';
    setTimeout(() => { input.type = 'password'; info.classList.add('hidden'); }, 8000);
  } else {
    info.textContent = 'Password tidak tersedia — perlu diisi ulang';
    info.classList.remove('hidden');
    info.style.color = '#ef4444';
  }
}
</script>
@endpush

@endsection