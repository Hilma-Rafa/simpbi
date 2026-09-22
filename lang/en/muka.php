<?php

/*
 * Landing page copy in English — the mirror of lang/id/muka.php.
 *
 * The keys stay in Indonesian on purpose: they are read alongside the Blade
 * template and the rest of the codebase, which is written in Indonesian, so
 * translating the keys as well would only make the two files harder to match
 * line by line.
 *
 * Institution names follow the official English style used by BPS itself
 * ("BPS-Statistics Indonesia"), not a literal word-for-word translation.
 */

return [

    'meta' => [
        'judul' => 'SIMPBI — Goods Request and Inventory Management Information System',
        'deskripsi' => 'SIMPBI — Goods Request and Inventory Management Information System. General Affairs Sub-Division, BPS-Statistics of West Jakarta Municipality.',
    ],

    'nav' => [
        'tentang' => 'About',
        'fitur' => 'Features',
        'alur' => 'Process',
        'verifikasi' => 'Verification',
        'masuk' => 'Sign In',
        'buka_menu' => 'Open menu',
        'tutup_menu' => 'Close menu',
        'bahasa' => 'Language',
    ],

    /* Language names are written in their own language and are identical in
       both files, so a visitor who does not read the current page language
       still recognises them as names rather than as translations. */
    'bahasa' => [
        'id' => 'Indonesia',
        'en' => 'English',
    ],

    'hero' => [
        'badge' => 'Internal System',
        'badge_satker' => ' · BPS West Jakarta',
        'nama_panjang' => 'Goods Request and Inventory Management Information System',
        'deskripsi' => 'Handle requests, supply availability, and goods distribution in a single process that is integrated and traceable end to end.',
        'tombol_masuk' => 'Sign In',
        'tombol_alur' => 'See the Process',
        'satker' => 'General Affairs Sub-Division · BPS-Statistics of West Jakarta Municipality',
        'angka' => [
            ['5', 'User Roles'],
            ['8', 'Work Teams'],
            ['6', 'Approval Stages'],
            ['2', 'Notification Channels'],
        ],
    ],

    'tentang' => [
        'label' => 'Why SIMPBI',
        'judul' => 'One clear process, from request to ratification.',
        'paragraf' => 'It replaces scattered manual bookkeeping with a workflow that is standardised, easy to monitor, and possible to trace back.',
        'kartu' => [
            ['judul' => 'Stock is easier to watch', 'isi' => 'Physical, held, and available stock figures are presented in a far more structured way.'],
            ['judul' => 'Requests are more consistent', 'isi' => 'The catalogue is the single reference for the items named in every submission.'],
            ['judul' => 'The process can be traced', 'isi' => 'Every request carries a status and a recorded history of what happened to it.'],
        ],
    ],

    'fitur' => [
        'label' => 'Features',
        'judul' => 'Built around the daily work of the teams.',
        'kartu' => [
            ['judul' => 'Item Catalogue', 'isi' => 'A standardised list of supply items to base every submission on.'],
            ['judul' => 'Requests & Approvals', 'isi' => 'A tiered submission flow, with approvals matched to each level of authority.'],
            ['judul' => 'Stock Control', 'isi' => 'Hold, release, and conversion mechanisms keep the stock figures accurate.'],
            ['judul' => 'Monitoring & Reports', 'isi' => 'Dashboards tailored per role, and reports that can be exported.'],
            ['judul' => 'Asset Transfer Records', 'isi' => 'Placement, redistribution, and transfer of equipment and machinery assets.'],
            ['judul' => 'Handover Deed + QR Check', 'isi' => 'Handover deeds carrying a digital signature and a QR code.'],
        ],
    ],

    'alur' => [
        'label' => 'Request Flow',
        'judul' => 'Seven stages, one clear trail.',
        'peran' => 'Team Lead',
        'paragraf' => 'When the requester is a :peran, the team approval stage is skipped and the request goes straight to warehouse verification.',
        'tahap' => [
            'Submission',
            'Team Lead Approval',
            'Warehouse Verification',
            'Sub-Division Head Approval',
            'Preparation',
            'Receipt',
            'Ratification',
        ],
        'catatan_ketua' => 'Skipped when the requester is a Team Lead',
        'selesai' => 'Completed & archived',
    ],

    'verifikasi' => [
        'label' => 'Document Verification',
        'judul' => 'Every official document can be scanned and checked for authenticity.',
        'paragraf' => 'Request receipts and asset handover deeds carry a QR code. Scanning it opens a verification page that states whether the document is genuine, without exposing any sensitive data.',
        'daftar' => [
            'Document number & type',
            'Date of ratification',
            'Authenticity status',
        ],
        'demo' => [
            'instansi' => 'BPS-STATISTICS INDONESIA',
            'kota' => 'West Jakarta Municipality',
            'label' => 'Demo',
            'jenis' => 'Goods Request Receipt',
            'status' => 'Genuine document',
            'token' => 'Token:',
            'catatan' => 'Illustration only. Not an official document.',
        ],
    ],

    'ajakan' => [
        'judul' => 'Sign in to start working.',
        'paragraf' => 'Reach SIMPBI with the account issued to you by the system administrator.',
        'tombol' => 'Sign In',
    ],

    'kaki' => [
        'satker' => 'General Affairs Sub-Division · BPS West Jakarta',
        'hak_cipta' => '© :tahun BPS-Statistics of West Jakarta Municipality. Internal system.',
        'bantuan_judul' => 'Having trouble signing in?',
        'bantuan_tombol' => 'Contact the General Affairs Sub-Division',
        'hubungi_judul' => 'Contact Us',
        'hubungi_telepon' => 'Phone',
        'hubungi_email' => 'Email',
        'alamat_judul' => 'Office Address',
        'alamat_satker' => 'BPS-Statistics of West Jakarta Municipality',
        'alamat_jalan' => 'Jl. Raya Kebayoran Lama No. 5A, Sukabumi Selatan, Kebun Jeruk, West Jakarta 11550',
        'alamat_peta' => 'View on map',
        'medsos_judul' => 'Social Media',
    ],

];
