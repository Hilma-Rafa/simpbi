<?php

namespace App\Http\Middleware;

use App\Filament\Pages\GantiKataSandi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan pengguna yang masih memakai kata sandi awal.
 *
 * Akun hasil impor massal berkata sandi seragam yang dibagikan Administrator
 * sekaligus kepada banyak orang. Kata sandi semacam itu aman hanya selama
 * berumur pendek, dan satu-satunya cara memastikannya berumur pendek adalah
 * menahan pemiliknya di satu halaman sampai ia menggantinya.
 *
 * Yang ditahan hanyalah permintaan halaman biasa. Permintaan Livewire
 * dibiarkan lewat, sebab halaman penggantian itu sendiri bekerja lewat
 * Livewire — mengalihkannya akan membuat formulirnya tidak mungkin dikirim,
 * sehingga pengguna terkurung tanpa jalan keluar sama sekali.
 */
class PaksaGantiKataSandi
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! $pengguna?->harus_ganti_sandi) {
            return $next($request);
        }

        if ($this->dikecualikan($request)) {
            return $next($request);
        }

        return redirect()->to(GantiKataSandi::getUrl());
    }

    /**
     * Permintaan yang tetap boleh lewat: halaman penggantian itu sendiri,
     * seluruh lalu lintas Livewire, dan keluar dari sistem — pengguna yang
     * belum siap mengganti kata sandinya harus tetap dapat keluar.
     */
    protected function dikecualikan(Request $request): bool
    {
        return $request->routeIs('filament.admin.pages.ganti-kata-sandi')
            || $request->is('livewire/*')
            || $request->is('livewire-*/*')
            || $request->routeIs('filament.admin.auth.logout')
            || $request->isMethod('POST') && $request->routeIs('*logout*');
    }
}
