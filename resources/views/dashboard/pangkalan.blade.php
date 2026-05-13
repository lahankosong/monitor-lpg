@extends('layouts.app')
@section('title', 'Daftar Pangkalan')

@section('content')

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
  <div>
    <h1 class="text-lg font-semibold">Daftar 47 Pangkalan</h1>
    <p class="text-xs text-gray-500 mt-0.5">
      Token aktif: <span class="font-medium text-green-600">{{ $tokenOk }}</span> pangkalan ·
      Scrape hari ini: <span class="font-medium">{{ $totalSaved }}</span> transaksi
    </p>
  </div>

  {{-- Scrape semua yang token-nya masih valid --}}
  <form action="{{ route('dashboard.pangkalan.scrape-all') }}" method="POST"
        class="flex items-center gap-2">
    @csrf
    <input type="date" name="from" value="{{ now()->startOfWeek()->toDateString() }}"
           class="border border-gray-300 rounded px-2 py-1.5 text-sm">
    <input type="date" name="to"   value="{{ now()->toDateString() }}"
           class="border border-gray-300 rounded px-2 py-1.5 text-sm">
    <button type="submit"
            class="bg-blue-600 text-white rounded px-4 py-1.5 text-sm hover:bg-blue-700"
            onclick="return confirm('Scrape {{ $tokenOk }} pangkalan yang tokennya aktif?')">
      Scrape Semua ({{ $tokenOk }} aktif)
    </button>
  </form>
</div>

{{-- Info cara kerja extension --}}
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 text-sm">
  <p class="font-medium text-blue-800 mb-1">⚡ Cara kerja otomatis</p>
  <p class="text-blue-700 text-xs">
    Setiap kali Anda login ke akun pangkalan di browser (dengan extension aktif),
    token otomatis terkirim ke sini dan data langsung di-scrape.
    Cukup login 47 akun satu per satu — semuanya tersimpan otomatis.
  </p>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 text-sm text-green-800">
  ✓ {{ session('success') }}
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Nama Pangkalan</th>
        <th class="text-left px-4 py-3">ID Pangkalan</th>
        <th class="text-center px-4 py-3">Token</th>
        <th class="text-right px-4 py-3">Berlaku s/d</th>
        <th class="text-right px-4 py-3">Scrape Terakhir</th>
        <th class="text-right px-4 py-3">Txn Tersimpan</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($pangkalans as $p)
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-3">
          <form action="{{ route('dashboard.pangkalan.label', $p['id']) }}" method="POST"
                class="flex items-center gap-2">
            @csrf
            <input name="label" value="{{ $p['label'] }}"
                   class="border-0 border-b border-transparent hover:border-gray-300
                          focus:border-blue-500 outline-none px-0 py-0.5 text-sm font-medium
                          bg-transparent w-40"
                   placeholder="Beri nama pangkalan...">
            <button type="submit" class="text-xs text-gray-400 hover:text-blue-600">✓</button>
          </form>
        </td>
        <td class="px-4 py-3 font-mono text-xs text-gray-400">
          {{ substr($p['id'], 0, 8) }}...
        </td>
        <td class="px-4 py-3 text-center">
          @if($p['token_ok'])
            <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
            <span class="text-xs text-green-600 ml-1">Aktif</span>
          @else
            <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
            <span class="text-xs text-red-500 ml-1">Expired</span>
          @endif
        </td>
        <td class="px-4 py-3 text-right text-xs text-gray-500">
          {{ $p['expires_at'] ?? '—' }}
        </td>
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
        <td class="px-4 py-3 text-right text-xs font-medium">
          {{ $p['last_saved'] ?: '—' }}
        </td>
        <td class="px-4 py-3 text-right">
          <a href="{{ route('dashboard.nik.list', ['pangkalan_id' => $p['id']]) }}"
             class="text-xs text-blue-600 hover:underline">NIK →</a>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
          Belum ada pangkalan. Pasang Chrome Extension dan login ke akun pangkalan.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<p class="text-xs text-gray-400 mt-2">
  Nama pangkalan bisa diedit langsung di kolom "Nama Pangkalan" — klik nama lalu tekan ✓
</p>

@endsection
