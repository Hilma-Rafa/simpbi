<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('verifikasi.judul_bast') }}</title>
    <style>
        :root { --biru: #14539A; --hijau: #17663F; --merah: #A32B2B; --abu: #5B6570; --garis: #E3E5E9; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px 16px 48px; background: #F5F6F8; color: #1F2933;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.55;
        }
        .wadah { max-width: 640px; margin: 0 auto; }
        .kepala { text-align: center; padding: 8px 0 20px; }
        .kepala h1 { font-size: 15px; font-weight: 700; letter-spacing: .3px; color: var(--biru); margin: 0; }
        .kepala p { margin: 2px 0 0; font-size: 13px; color: var(--abu); }
        .kartu { background: #fff; border: 1px solid var(--garis); border-radius: 10px; overflow: hidden; }
        .pita { padding: 20px 24px; border-bottom: 1px solid var(--garis); }
        .pita.sah   { background: #F0F7F3; border-left: 5px solid var(--hijau); }
        .pita.tidak { background: #FBF0F0; border-left: 5px solid var(--merah); }
        .pita h2 { margin: 0; font-size: 19px; font-weight: 700; }
        .pita.sah h2   { color: var(--hijau); }
        .pita.tidak h2 { color: var(--merah); }
        .pita p { margin: 4px 0 0; font-size: 13px; color: var(--abu); }
        .isi { padding: 20px 24px; }
        dl { margin: 0; }
        .baris { display: flex; gap: 16px; padding: 9px 0; border-bottom: 1px solid #F1F3F5; }
        .baris:last-child { border-bottom: 0; }
        dt { flex: 0 0 150px; color: var(--abu); font-size: 13px; margin: 0; }
        dd { flex: 1; margin: 0; font-weight: 500; }
        .kaki { text-align: center; margin-top: 20px; font-size: 12px; color: var(--abu); }

        /* Penukar bahasa. Dijajarkan di atas kepala halaman, cukup kecil agar
           tidak merebut perhatian dari pita status keaslian yang justru menjadi
           alasan orang membuka halaman ini. */
        .bahasa { display: flex; justify-content: flex-end; gap: 6px; padding: 0 0 10px; }
        .bahasa a {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 9px; border: 1px solid var(--garis); border-radius: 999px;
            background: #fff; color: var(--abu);
            font-size: 12px; font-weight: 600; line-height: 1; text-decoration: none;
        }
        .bahasa a.aktif { color: var(--biru); border-color: var(--biru); background: #F0F5FB; }
        /* Bendera diberi garis dalam supaya bagian putihnya tidak lenyap di
           atas latar kartu yang juga putih. */
        .bahasa .bendera { width: 16px; height: 11px; border-radius: 2px; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .18); }

        @media (max-width: 480px) { .baris { flex-direction: column; gap: 2px; } dt { flex: none; } }
    </style>
</head>
<body>
@include('komponen.lambang-bendera')
<div class="wadah">
    @include('verifikasi._penukar-bahasa')

    <div class="kepala">
        <h1>{{ __('verifikasi.instansi') }}</h1>
        <p>{{ __('verifikasi.subjudul') }}</p>
    </div>

    @if ($bast)
        <div class="kartu">
            <div class="pita sah">
                <h2>{{ __('verifikasi.sah') }}</h2>
                <p>{{ __('verifikasi.bast.sah_isi') }}</p>
            </div>
            <div class="isi">
                <dl>
                    <div class="baris"><dt>{{ __('verifikasi.bast.nomor') }}</dt><dd>{{ $bast->nomor_bast }}</dd></div>
                    <div class="baris"><dt>{{ __('verifikasi.bast.jenis') }}</dt><dd>{{ __('verifikasi.bast.jenis_nilai') }}</dd></div>
                    {{-- Penyebutan NUP disusun di berkas terjemahan, bukan
                         dirangkai di sini, sebab dalam bahasa Inggris singkatan
                         itu tidak dikenal dan perlu ditulis panjang. --}}
                    <div class="baris"><dt>{{ __('verifikasi.bast.aset') }}</dt>
                        <dd>{{ __('verifikasi.bast.aset_nilai', ['nama' => $bast->aset?->nama_aset, 'nup' => $bast->aset?->nup]) }}</dd>
                    </div>
                    <div class="baris"><dt>{{ __('verifikasi.bast.tim_asal') }}</dt><dd>{{ $bast->timAsal?->nama_tim ?? '-' }}</dd></div>
                    <div class="baris"><dt>{{ __('verifikasi.bast.tim_tujuan') }}</dt><dd>{{ $bast->timTujuan?->nama_tim ?? '-' }}</dd></div>
                    <div class="baris"><dt>{{ __('verifikasi.bast.disahkan_pada') }}</dt><dd>{{ $bast->disahkan_at?->translatedFormat('d F Y, H:i') ?? '-' }}</dd></div>
                    <div class="baris"><dt>{{ __('verifikasi.bast.disahkan_oleh') }}</dt><dd>{{ $bast->disahkanOleh?->name ?? __('verifikasi.pengesah_bawaan') }}</dd></div>
                    <div class="baris">
                        <dt>{{ __('verifikasi.bast.status') }}</dt>
                        <dd>{{ $bast->dikonfirmasi_at ? __('verifikasi.bast.status_selesai') : __('verifikasi.bast.status_menunggu') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @else
        <div class="kartu">
            <div class="pita tidak">
                <h2>{{ __('verifikasi.tidak_ditemukan') }}</h2>
                <p>{{ __('verifikasi.bast.tidak_isi') }}</p>
            </div>
            <div class="isi">
                <p style="margin:0;color:var(--abu);font-size:14px;">
                    {{ __('verifikasi.imbauan') }}
                </p>
            </div>
        </div>
    @endif

    <p class="kaki">{{ __('verifikasi.kaki') }}</p>
</div>
</body>
</html>
