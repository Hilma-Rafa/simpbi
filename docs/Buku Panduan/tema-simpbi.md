# Token Tema SIMPBI

Diambil dari `resources/css/filament/admin/theme.css` dan `resources/views/welcome.blade.php` per commit `35a1a7a`. Hanya nilai yang benar-benar ada di berkas ini — tidak ada nilai yang diperkirakan atau diunduh.

## Warna {#L-WARNA}

| Token | Nilai hex | Pemakaian |
|---|---|---|
| `--color-navy` | `#0B2A5B` | Warna utama identitas SIMPBI — panel kiri halaman masuk, wordmark |
| `--color-brand` | `#1557A6` | Biru primer — aksi utama, tautan |
| `--color-brand-light` | `#EAF3FB` | Tint biru — latar lembut elemen bertema biru |
| `--color-accent` | `#F59E0B` | Amber/oranye — aksen, CTA landing page, status semantik |
| `--color-accent-light` | `#FFF7E6` | Tint amber |
| `--color-bps-orange` | `#E18939` | Oranye identitas BPS (disampel dari `logo-bps.png`) — dipakai terbatas pada kotak tint ikon dan status sinkronisasi |
| `--color-bps-orange-dark` | `#AE611A` | Varian gelap oranye BPS (kontras ≥4.5:1 WCAG) |
| `--color-bps-orange-light` | `#FBF1E7` | Varian tint oranye BPS |
| `--color-bps-green` | `#75B547` | Hijau identitas BPS (disampel dari `logo-bps.png`) |
| `--color-bps-green-dark` | `#548233` | Varian gelap hijau BPS |
| `--color-bps-green-light` | `#EEF6E9` | Varian tint hijau BPS |
| `--simpbi-navy-bilah` | `#0B2A5B` | Navy bilah/kartu tertentu |
| `--simpbi-navy-sisi` | `#0A2450` | Navy sisi (sedikit lebih gelap) |

Warna status semantik (success/danger dsb.) memakai palet bawaan Filament, tidak ditulis ulang sebagai token SIMPBI tersendiri di berkas ini — [PERLU KONFIRMASI bila penyusun dokumen akhir memerlukan nilai hex persisnya dari palet Filament terpasang].

## Font {#L-FONT}

| Token | Famili | Sumber |
|---|---|---|
| `--simpbi-huruf-judul` | `'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif` | Plus Jakarta Sans dari `fonts.bunny.net` (eksternal); Inter tersedia lokal (`public/fonts/filament/filament/inter/`, bawaan Filament) |
| `--simpbi-huruf-wordmark` | `'Space Grotesk', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif` | Space Grotesk dari `fonts.bunny.net` (eksternal) |
| `--font-display` (halaman muka publik) | `'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif` | sama seperti di atas |
| `--font-wordmark` (halaman muka publik) | `'Space Grotesk', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif` | sama seperti di atas |

Pemakaian: `--simpbi-huruf-judul` pada judul halaman panel (`.fi-header-heading`) dan judul kartu/section (`.fi-section-header-heading`); `--simpbi-huruf-wordmark` pada wordmark "SIMPBI" di bilah atas panel. Pasangan `--font-display`/`--font-wordmark` dipakai versi publik (halaman muka `/`) untuk elemen yang setara.

## Radius {#L-RADIUS}

Sistem tidak memakai satu token radius tunggal — nilainya bervariasi per komponen, dari `0.3rem` (elemen kecil) sampai `24px`/`1.5rem` (kartu navy halaman masuk, lebar ≥1024px). Nilai yang paling sering muncul: `0.375rem`, `0.5rem`, `0.625rem`, `0.75rem`, `0.875rem`, `12px`, `14px`, `16px`, dan `9999px` (penuh/pil, dipakai badge, toggle tema, avatar).

## Logo dan Berkas Font Lokal {#L-ASET}

- Logo BPS: `public/images/logo-bps.png`.
- Font lokal (Inter, bawaan Filament, sudah dilokalkan — tidak memuat dari jaringan): `public/fonts/filament/filament/inter/` (berkas `.woff2` per subset bahasa, mis. `inter-latin-wght-normal-*.woff2`).
- Plus Jakarta Sans dan Space Grotesk **belum** dilokalkan — keduanya masih dimuat dari `fonts.bunny.net` (jaringan eksternal).

## Gaya Lencana / Tombol / Tabel {#L-KOMPONEN}

Mengikuti design system bawaan Filament v5 (warna semantik per status: success/warning/danger/info/gray) tanpa dirombak — kustomisasi SIMPBI pada `theme.css` berupa penambahan token warna/font BPS di atas, komponen navy khusus halaman masuk dan dropdown profil, serta kotak tint ikon (§`.simpbi-tint-icon`) untuk strip status sinkronisasi. Tabel, lencana status, dan tombol standar tetap memakai komponen Filament apa adanya. [PERLU KONFIRMASI: penyusun dokumen akhir sebaiknya mengambil nilai persis bayangan (box-shadow) dan warna lencana per status langsung dari tangkapan layar bagian J, sebab nilainya mengikuti tema Filament bawaan yang tidak ditulis ulang sebagai token SIMPBI khusus di `theme.css`.]
