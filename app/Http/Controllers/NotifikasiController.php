<?php
namespace App\Http\Controllers;

use App\Services\NotifikasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotifikasiController extends Controller
{
    /** API — jumlah belum dibaca (untuk badge topbar polling) */
    public function count()
    {
        $count = NotifikasiService::countBelumBaca(auth()->id());
        return response()->json(['count' => $count]);
    }

    /** API — dropdown notifikasi terbaru */
    public function terbaru()
    {
        $notifs = NotifikasiService::terbaru(auth()->id(), 10);
        return response()->json([
            'notifs' => $notifs->map(fn($n) => [
                'id'         => $n->id,
                'tipe'       => $n->tipe,
                'icon'       => NotifikasiService::icon($n->tipe),
                'warna'      => NotifikasiService::warna($n->tipe),
                'judul'      => $n->judul,
                'pesan'      => $n->pesan,
                'url'        => $n->url,
                'is_read'    => (bool)$n->is_read,
                'waktu'      => \Carbon\Carbon::parse($n->created_at)->diffForHumans(),
            ]),
        ]);
    }

    /** Halaman inbox notifikasi */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'semua');

        $notifs = DB::table('notifikasis')
            ->where('user_id', auth()->id())
            ->when($filter === 'belum', fn($q) => $q->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        $totalBelumBaca = NotifikasiService::countBelumBaca(auth()->id());

        return view('notifikasi.index', compact('notifs','totalBelumBaca','filter'));
    }

    /** Tandai satu sebagai dibaca + redirect ke URL notifikasi */
    public function baca(int $id)
    {
        $notif = DB::table('notifikasis')
            ->where('id', $id)->where('user_id', auth()->id())->first();

        if ($notif) {
            NotifikasiService::baca($id, auth()->id());
            if ($notif->url) return redirect($notif->url);
        }
        return back();
    }

    /** Tandai semua dibaca */
    public function bacaSemua()
    {
        NotifikasiService::bacaSemua(auth()->id());
        return back()->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }
}
