@extends('layouts.app')
@section('title', 'Referensi Harga')

@section('content')
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Referensi Harga</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Master harga yang digunakan di seluruh transaksi</p>
  </div>
  <button onclick="openModal('modal-tambah')"
          style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
    + Tambah Harga
  </button>
</div>

{{-- Harga Aktif Summary --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
  @foreach($hargaAktif as $kategori => $h)
  @php
    $colors = [
      'tebus_refil'    => '#EF4444',
      'jual_pangkalan' => '#10B981',
      'tabung_perdana' => '#3B82F6',
      'sewa_tabung'    => '#F59E0B',
      'lainnya'        => '#6B7280',
    ];
    $labels = [
      'tebus_refil'    => 'Tebus Refil',
      'jual_pangkalan' => 'Jual Pangkalan',
      'tabung_perdana' => 'Tabung Perdana',
      'sewa_tabung'    => 'Sewa Tabung',
      'lainnya'        => 'Lainnya',
    ];
  @endphp
  <div class="stat-card" style="border-left:3px solid {{ $colors[$kategori] }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">{{ $labels[$kategori] }}</p>
    @if($h)
      <p style="font-size:20px;font-weight:700;color:var(--text);margin-top:4px">Rp {{ number_format($h->harga) }}</p>
      <p style="font-size:11px;color:var(--muted)">per {{ $h->satuan }} · berlaku {{ $h->berlaku_mulai->format('d/m/Y') }}</p>
    @else
      <p style="font-size:14px;color:#EF4444;margin-top:6px">Belum diatur</p>
    @endif
  </div>
  @endforeach
</div>

{{-- Tabel semua harga --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Nama Item','Kategori','Harga','Satuan','Berlaku Mulai','Berlaku Sampai',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($hargaList as $h)
      @php
        $isAktif = $h->is_active &&
            $h->berlaku_mulai->lte(now()) &&
            (! $h->berlaku_sampai || $h->berlaku_sampai->gte(now()));
      @endphp
      <tr style="border-top:1px solid var(--border);{{ $isAktif ? 'background:rgba(16,185,129,.03)' : '' }}">
        <td style="padding:11px 14px">
          <span style="font-weight:600;color:var(--text)">{{ $h->nama_item }}</span>
          @if($isAktif)
            <span style="margin-left:6px;background:#D1FAE5;color:#065F46;font-size:10px;padding:1px 6px;border-radius:99px">Aktif</span>
          @endif
        </td>
        <td style="padding:11px 14px;color:var(--muted)">{{ $h->kategori_label }}</td>
        <td style="padding:11px 14px;font-weight:700;color:var(--text)">Rp {{ number_format($h->harga) }}</td>
        <td style="padding:11px 14px;color:var(--muted)">{{ $h->satuan }}</td>
        <td style="padding:11px 14px">{{ $h->berlaku_mulai->format('d/m/Y') }}</td>
        <td style="padding:11px 14px;color:var(--muted)">{{ $h->berlaku_sampai?->format('d/m/Y') ?? '—' }}</td>
        <td style="padding:11px 14px">
          <form action="{{ route('dashboard.agen.akuntansi.harga.destroy', $h) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus data harga ini?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">
        Belum ada data harga — tambah harga tebus refil terlebih dahulu
      </td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $hargaList->links() }}</div>
</div>

{{-- Modal Tambah --}}
<div id="modal-tambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-tambah')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:500px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Tambah Harga Referensi</h3>
      <button onclick="closeModal('modal-tambah')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.harga.store') }}" method="POST" style="padding:20px 24px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="grid-column:1/-1">
          <label class="flabel">Nama Item *</label>
          <input name="nama_item" required placeholder="mis: Harga Tebus Refil Mei 2026" class="finput">
        </div>
        <div>
          <label class="flabel">Kategori *</label>
          <select name="kategori" required class="finput">
            @foreach($kategoriList as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="flabel">Satuan</label>
          <input name="satuan" value="tabung" class="finput" placeholder="tabung">
        </div>
        <div>
          <label class="flabel">Harga (Rp) *</label>
          <input name="harga" type="number" required min="0" placeholder="15520" class="finput">
        </div>
        <div>
          <label class="flabel">Berlaku Mulai *</label>
          <input name="berlaku_mulai" type="date" required value="{{ now()->toDateString() }}" class="finput">
        </div>
        <div style="grid-column:1/-1">
          <label class="flabel">Berlaku Sampai <span style="font-weight:400;opacity:.6">(kosong = tidak terbatas)</span></label>
          <input name="berlaku_sampai" type="date" class="finput">
        </div>
        <div style="grid-column:1/-1">
          <label class="flabel">Keterangan</label>
          <input name="keterangan" class="finput" placeholder="Catatan opsional">
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Simpan</button>
        <button type="button" onclick="closeModal('modal-tambah')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal('modal-tambah'); });
</script>
@endpush
