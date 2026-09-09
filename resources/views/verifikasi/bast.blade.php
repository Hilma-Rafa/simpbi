<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi BAST — BPS Kota Jakarta Barat</title>
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
        @media (max-width: 480px) { .baris { flex-direction: column; gap: 2px; } dt { flex: none; } }
    </style>
</head>
<body>
<div class="wadah">
    <div class="kepala">
        <h1>BADAN PUSAT STATISTIK KOTA JAKARTA BARAT</h1>
        <p>Verifikasi Keaslian Dokumen</p>
    </div>

    @if ($bast)
        <div class="kartu">
            <div class="pita sah">
                <h2>Dokumen Sah</h2>
                <p>Berita Acara Serah Terima mutasi aset terdaftar dan telah disahkan pada sistem.</p>
            </div>
            <div class="isi">
                <dl>
                    <div class="baris"><dt>Nomor BAST</dt><dd>{{ $bast->nomor_bast }}</dd></div>
                    <div class="baris"><dt>Jenis Dokumen</dt><dd>Berita Acara Serah Terima Mutasi Aset</dd></div>
                    <div class="baris"><dt>Aset</dt><dd>{{ $bast->aset?->nama_aset }} (NUP {{ $bast->aset?->nup }})</dd></div>
                    <div class="baris"><dt>Unit Asal</dt><dd>{{ $bast->timAsal?->nama_tim ?? '-' }}</dd></div>
                    <div class="baris"><dt>Unit Tujuan</dt><dd>{{ $bast->timTujuan?->nama_tim ?? '-' }}</dd></div>
                    <div class="baris"><dt>Disahkan Pada</dt><dd>{{ $bast->disahkan_at?->translatedFormat('d F Y, H:i') ?? '-' }}</dd></div>
                    <div class="baris"><dt>Disahkan Oleh</dt><dd>{{ $bast->disahkanOleh?->name ?? 'Kepala Sub Bagian Umum' }}</dd></div>
                    <div class="baris">
                        <dt>Status</dt>
                        <dd>{{ $bast->dikonfirmasi_at ? 'Selesai administratif (telah dikonfirmasi penerima)' : 'Menunggu konfirmasi penerima' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @else
        <div class="kartu">
            <div class="pita tidak">
                <h2>Dokumen Tidak Ditemukan</h2>
                <p>Kode verifikasi tidak terdaftar atau dokumen belum disahkan. Keaslian tidak dapat dipastikan.</p>
            </div>
            <div class="isi">
                <p style="margin:0;color:var(--abu);font-size:14px;">
                    Apabila Anda memperoleh dokumen ini dari pihak yang mengatasnamakan
                    Sub Bagian Umum BPS Kota Jakarta Barat, mohon lakukan konfirmasi secara langsung.
                </p>
            </div>
        </div>
    @endif

    <p class="kaki">Halaman verifikasi ini dihasilkan secara otomatis oleh sistem.</p>
</div>
</body>
</html>
