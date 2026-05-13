<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Cek apakah user punya role yang diizinkan
     * Pemakaian di route: ->middleware('role:direktur,manajer')
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!in_array($user->role, $roles)) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk: '.implode(', ', $roles));
        }

        return $next($request);
    }
}
