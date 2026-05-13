@extends('layouts.app')
@section('title', 'Manager Pangkalan')

@section('content')

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
  <div>
    <h1 class="text-lg font-semibold">Manager Pangkalan</h1>
    <p class="text-xs text-gray-500 mt-0.5">
      Klik <strong>Scrape</strong> pada pangkalan mana saja — sistem otomatis pakai token/cookies tersimpan.
    </p>
  </div>
  <div class="flex gap-2 flex-wrap">
    <button onclick="document.getElementById('modal-tambah').classList.remove('hidden')"
            class="text-sm border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50">
      + Tambah Pangkalan
    </button>
    <a href="{{ route('dashboard.batch.index') }}"
       class="text-sm bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700">
      Batch Scrape Semua →
    </a>
  </div>
</div>

{{-- Status bar --}}
<div class="grid grid-cols-3 gap-3 mb-5">
  <div class="bg-green-50 border border-green-200 rounded-xl p-3 text-center">
    <p class="text-2xl font-semibold text-green-700">{{ $siap }}</p>
    <p class="text-xs text-green-600">Token aktif</p>
  </div>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
    <p class="text-2xl font-semibold text-amber-700">{{ $needsRelogin }}</p>
    <p class="text-xs text-amber-600">Perlu login ulang</p>
  </div>
  <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 text-center">
    <p class="text-2xl font-semibold text-gray-700">{{ $total }}</p>
    <p class="text-xs text-gray-500">Total pangkalan</p>
  </div>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 text-sm text-green-800">✓ {{ session('success') }}</div>
@endif
@if(session('warning'))
<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-sm text-amber-800">⚠ {{ session('warning') }}</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-800">
  @foreach($errors->all() as $e)✗ {{ $e }}<br>@endforeach
</div>
@endif

{{-- Tabel pangkalan --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Pangkalan</th>
        <th class="text-left px-4 py-3">Username</th>
        <th class="text-center px-4 py-3">Status</th>
        <th class="text-right px-4 py-3">Login Terakhir</th>
        <th class="text-right px-4 py-3">Scrape Terakhir</th>
        <th class="text-right px-4 py-3">Txn</th>
        <th class="px-4 py-3 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($pangkalans as $p)
      <tr class="{{ $p['needs_relogin'] ? 'bg-amber-50/40' : 'hover:bg-gray-50' }}">
        <td class="px-4 py-3 font-medium">{{ $p['label'] }}</td>
        <td class="px-4 py-3 text-xs text-gray-500">{{ $p['username'] }}</td>
        <td class="px-4 py-3 text-center">
          @if($p['token_valid'])
            <span class="badge-aman">Token aktif s/d {{ $p['token_expires'] }}</span>
          @else
            <span class="badge-warn">Perlu login ulang</span>
          @endif
        </td>
        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $p['last_login'] }}</td>
        <td class="px-4 py-3 text-right text-xs text-gray-500">
          @if($p['last_scrape'])
            {{ $p['last_scrape'] }}
            <span class="{{ $p['last_status']==='success' ? 'text-green-600':'text-red-500' }}">
              ({{ $p['last_status'] }})
            </span>
          @else
            <span class="text-gray-300">Belum pernah</span>
          @endif
        </td>
        <td class="px-4 py-3 text-right text-xs font-medium">{{ $p['last_saved'] ?: '—' }}</td>
        <td class="px-4 py-3 text-right">
          <div class="flex items-center justify-end gap-2">
            @if($p['needs_relogin'])
              <a href="{{ route('dashboard.token.input') }}"
                 class="text-xs text-amber-600 hover:underline">Login ulang</a>
            @else
              <form action="{{ route('dashboard.pangkalan.scrape-one', $p['id']) }}" method="POST"
                    class="inline scrape-form">
                @csrf
                <input type="hidden" name="from" class="input-from">
                <input type="hidden" name="to"   class="input-to">
                <button type="submit"
                        class="text-xs bg-blue-600 text-white rounded px-3 py-1 hover:bg-blue-700">
                  Scrape
                </button>
              </form>
            @endif
            <a href="{{ route('dashboard.nik.list') }}"
               class="text-xs text-gray-400 hover:text-gray-700">NIK</a>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
          Belum ada pangkalan. Klik "+ Tambah Pangkalan" atau gunakan
          <a href="{{ route('dashboard.batch.index') }}" class="text-blue-600 underline">Batch Scrape</a>
          dengan accounts.json.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- Modal: Tambah Pangkalan --}}
<div id="modal-tambah" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg">
    <h2 class="font-semibold text-base mb-1">Tambah Pangkalan</h2>
    <p class="text-xs text-gray-400 mb-4">
      Isi credentials pangkalan. Token akan diambil otomatis via Playwright saat pertama scrape.
    </p>
    <form action="{{ route('dashboard.pangkalan.store') }}" method="POST">
      @csrf
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Nama Pangkalan *</label>
            <input name="label" required placeholder="mis: Pangkalan Bu Sari"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Email/Username *</label>
            <input name="username" required placeholder="email@gmail.com"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Password/PIN *</label>
          <input type="password" name="password" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Bearer Token (opsional — untuk langsung aktif)</label>
          <textarea name="token" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:border-blue-500"
                    placeholder="eyJhbGci... (bisa dikosongkan, akan diambil otomatis saat scrape)"></textarea>
        </div>
      </div>
      <div class="flex gap-2 mt-4">
        <button type="submit" class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm hover:bg-blue-700">Simpan</button>
        <button type="button" onclick="document.getElementById('modal-tambah').classList.add('hidden')"
                class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">Batal</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
const fromVal = '{{ now()->startOfWeek()->toDateString() }}';
const toVal   = '{{ now()->toDateString() }}';
document.querySelectorAll('.scrape-form').forEach(form => {
  form.querySelector('.input-from').value = fromVal;
  form.querySelector('.input-to').value   = toVal;
});
</script>
@endpush
