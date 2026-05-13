@extends('layouts.app')
@section('title', 'Input Token Pangkalan')

@section('content')

<div class="max-w-2xl mx-auto">

  <div class="mb-5">
    <h1 class="text-lg font-semibold">Input Token Pangkalan</h1>
    <p class="text-xs text-gray-500 mt-1">
      Paste Bearer token dari browser → sistem langsung scrape data hari ini secara otomatis.
    </p>
  </div>

  {{-- Panduan cara ambil token --}}
  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
    <p class="font-medium text-blue-800 text-sm mb-3">📋 Cara ambil token (30 detik per pangkalan)</p>
    <div class="space-y-2">
      <div class="flex gap-3 text-xs text-blue-700">
        <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 font-bold">1</span>
        <span>Buka <strong>subsiditepatlpg.mypertamina.id</strong> → login akun pangkalan</span>
      </div>
      <div class="flex gap-3 text-xs text-blue-700">
        <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 font-bold">2</span>
        <span>Tekan <strong>F12</strong> → tab <strong>Network</strong> → ketik <code class="bg-blue-100 px-1 rounded">api-map</code> di kotak filter</span>
      </div>
      <div class="flex gap-3 text-xs text-blue-700">
        <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 font-bold">3</span>
        <span>Klik menu <strong>Laporan Penjualan</strong> di MyPertamina — request akan muncul di Network</span>
      </div>
      <div class="flex gap-3 text-xs text-blue-700">
        <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 font-bold">4</span>
        <span>Klik request yang muncul → tab <strong>Headers</strong> → scroll ke <strong>Request Headers</strong> → klik kanan nilai <strong>authorization</strong> → <strong>Copy value</strong></span>
      </div>
      <div class="flex gap-3 text-xs text-blue-700">
        <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 font-bold">5</span>
        <span>Paste di form bawah → klik <strong>Simpan & Scrape</strong></span>
      </div>
    </div>
  </div>

  {{-- Form input token --}}
  <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
    <form action="{{ route('dashboard.token.input') }}" method="POST" id="formToken">
      @csrf
      <div class="space-y-4">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Nama Pangkalan</label>
            <input name="label" id="labelInput"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
                   placeholder="mis: Pangkalan Bu Sari RT05">
            <p class="text-xs text-gray-400 mt-1">Untuk memudahkan identifikasi</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Rentang Scraping</label>
            <div class="flex gap-2">
              <input type="date" name="from" value="{{ now()->toDateString() }}"
                     class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-blue-500">
              <input type="date" name="to" value="{{ now()->toDateString() }}"
                     class="flex-1 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-blue-500">
            </div>
            <p class="text-xs text-gray-400 mt-1">Default: hari ini saja</p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">
            Bearer Token
            <span class="text-red-500">*</span>
          </label>
          <textarea name="token" id="tokenInput" rows="3" required
                    onpaste="handlePaste(event)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:border-blue-500 resize-none"
                    placeholder="Paste token di sini... (boleh dengan atau tanpa kata 'Bearer ')"></textarea>
          <div id="tokenInfo" class="mt-1 text-xs hidden">
            <span id="tokenStatus"></span>
          </div>
        </div>

      </div>

      <div class="flex items-center gap-3 mt-4">
        <button type="submit" id="btnSimpan"
                class="bg-blue-600 text-white rounded-lg px-5 py-2 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
          Simpan & Scrape Otomatis
        </button>
        <button type="button" onclick="document.getElementById('tokenInput').value='';document.getElementById('tokenInfo').classList.add('hidden')"
                class="border border-gray-300 rounded-lg px-4 py-2 text-sm hover:bg-gray-50">
          Reset
        </button>
        <span id="loadingMsg" class="text-xs text-gray-400 hidden">
          ⏳ Menyimpan token & scraping data...
        </span>
      </div>
    </form>
  </div>

  {{-- Hasil scraping terakhir --}}
  @if(session('scrape_result'))
  @php $result = session('scrape_result'); @endphp
  <div class="bg-{{ $result['success'] ? 'green' : 'red' }}-50 border border-{{ $result['success'] ? 'green' : 'red' }}-200 rounded-xl p-4 mb-5">
    <p class="font-medium text-{{ $result['success'] ? 'green' : 'red' }}-800 text-sm mb-1">
      {{ $result['success'] ? '✓ Berhasil' : '✗ Gagal' }}
    </p>
    <div class="text-xs text-{{ $result['success'] ? 'green' : 'red' }}-700 space-y-1">
      <p>Pangkalan: <strong>{{ $result['label'] }}</strong></p>
      @if($result['success'])
        <p>Transaksi tersimpan: <strong>{{ $result['saved'] }}</strong></p>
        <p>Periode: {{ $result['from'] }} s/d {{ $result['to'] }}</p>
      @else
        <p>Error: {{ $result['error'] }}</p>
      @endif
    </div>
    @if($result['success'])
    <a href="{{ route('dashboard.nik.list', ['from' => $result['from'], 'to' => $result['to']]) }}"
       class="inline-block mt-2 text-xs text-green-700 underline hover:text-green-900">
      Lihat data NIK →
    </a>
    @endif
  </div>
  @endif

  {{-- Antrian pangkalan yang sudah diinput hari ini --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-medium text-sm">Token Tersimpan Hari Ini</h2>
      <a href="{{ route('dashboard.pangkalan.list') }}" class="text-xs text-blue-600 hover:underline">
        Semua pangkalan →
      </a>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
        <tr>
          <th class="text-left px-4 py-2">Pangkalan</th>
          <th class="text-center px-4 py-2">Token</th>
          <th class="text-right px-4 py-2">Scrape Terakhir</th>
          <th class="text-right px-4 py-2">Tersimpan</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($pangkalans as $p)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-2.5 font-medium">{{ $p->label ?: substr($p->pangkalan_id,0,8).'...' }}</td>
          <td class="px-4 py-2.5 text-center">
            @if($p->token_expires_at && $p->token_expires_at->isFuture())
              <span class="text-xs text-green-600">● Aktif s/d {{ $p->token_expires_at->format('H:i') }}</span>
            @else
              <span class="text-xs text-red-400">● Expired</span>
            @endif
          </td>
          <td class="px-4 py-2.5 text-right text-xs text-gray-500">
            {{ $p->lastLog?->scraped_at?->format('H:i') ?? '—' }}
          </td>
          <td class="px-4 py-2.5 text-right text-xs font-medium">
            {{ $p->lastLog?->records_saved ?? '—' }}
          </td>
          <td class="px-4 py-2.5 text-right">
            {{-- Scrape ulang jika token masih aktif --}}
            @if($p->token_expires_at && $p->token_expires_at->isFuture())
            <form action="{{ route('dashboard.token.rescrape') }}" method="POST" class="inline">
              @csrf
              <input type="hidden" name="pangkalan_id" value="{{ $p->pangkalan_id }}">
              <input type="hidden" name="from" value="{{ now()->startOfWeek()->toDateString() }}">
              <input type="hidden" name="to" value="{{ now()->toDateString() }}">
              <button type="submit" class="text-xs text-blue-600 hover:underline">Scrape ulang</button>
            </form>
            @else
              <span class="text-xs text-gray-300">Token expired</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="px-4 py-6 text-center text-gray-400 text-xs">
            Belum ada token hari ini. Gunakan form di atas untuk menambahkan.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>

@endsection

@push('scripts')
<script>
// Auto-detect pangkalan ID dari JWT dan tampilkan info token
function handlePaste(e) {
  setTimeout(() => {
    const raw   = document.getElementById('tokenInput').value.trim();
    const token = raw.replace(/^Bearer\s+/i, '');
    const info  = document.getElementById('tokenInfo');
    const status = document.getElementById('tokenStatus');

    try {
      const parts   = token.split('.');
      const payload = JSON.parse(atob(parts[1].replace(/-/g,'+').replace(/_/g,'/')));
      const exp     = payload.exp ? new Date(payload.exp * 1000) : null;
      const now     = new Date();
      const isValid = exp && exp > now;
      const minsLeft = exp ? Math.round((exp - now) / 60000) : 0;

      info.classList.remove('hidden');

      if (isValid) {
        status.innerHTML = `<span class="text-green-600">✓ Token valid — berlaku ${minsLeft} menit lagi (s/d ${exp.toLocaleTimeString('id')})</span>`;
        // Auto-fill nama pangkalan jika kosong
        const labelInput = document.getElementById('labelInput');
        if (!labelInput.value) {
          labelInput.placeholder = `Pangkalan ${payload.sub?.substring(0,8)}...`;
        }
      } else {
        status.innerHTML = `<span class="text-red-500">✗ Token sudah expired sejak ${exp?.toLocaleTimeString('id')} — ambil token baru dari browser</span>`;
      }
    } catch(e) {
      info.classList.remove('hidden');
      status.innerHTML = '<span class="text-amber-500">⚠ Format token tidak dikenali — pastikan paste nilai Authorization lengkap</span>';
    }
  }, 50);
}

// Tampilkan loading saat form disubmit
document.getElementById('formToken').addEventListener('submit', function() {
  document.getElementById('btnSimpan').disabled = true;
  document.getElementById('loadingMsg').classList.remove('hidden');
});
</script>
@endpush
