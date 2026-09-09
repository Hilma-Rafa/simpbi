<?php

/*
 * English copy for the two document verification pages — the mirror of
 * lang/id/verifikasi.php.
 *
 * These pages are opened by whoever scans the QR code printed on a document,
 * so the reader is not necessarily a member of staff and not necessarily an
 * Indonesian speaker. That is why they are translated at all, unlike the
 * application panel, which only internal staff ever see.
 */

return [

    'judul_permintaan' => 'Document Verification — BPS West Jakarta',
    'judul_bast' => 'Handover Deed Verification — BPS West Jakarta',

    'instansi' => 'BPS-STATISTICS OF WEST JAKARTA MUNICIPALITY',
    'subjudul' => 'Document Authenticity Check',

    'sah' => 'Genuine Document',
    'tidak_ditemukan' => 'Document Not Found',

    'imbauan' => 'If this document reached you from someone acting on behalf of the General Affairs Sub-Division of BPS West Jakarta, please confirm with the office directly.',
    'kaki' => 'This verification page is generated automatically by the system.',

    'permintaan' => [
        'sah_isi' => 'This document is registered in the Goods Request and Inventory Management Information System.',
        'tidak_isi' => 'The verification code is not registered in the system. This document cannot be confirmed as genuine.',
        'nomor' => 'Document Number',
        'tim' => 'Requesting Team',
        'pemohon' => 'Requester Name',
        'tanggal' => 'Date Submitted',
        'disahkan_pada' => 'Ratified On',
        'disahkan_oleh' => 'Ratified By',
        'rincian' => 'Items Requested',
        'kolom_nama' => 'Item',
        'kolom_jumlah' => 'Quantity',
        'kolom_satuan' => 'Unit',
    ],

    'bast' => [
        'sah_isi' => 'This asset transfer handover deed is registered and has been ratified in the system.',
        'tidak_isi' => 'The verification code is not registered, or the document has not been ratified. Its authenticity cannot be confirmed.',
        'nomor' => 'Deed Number',
        'jenis' => 'Document Type',
        'jenis_nilai' => 'Asset Transfer Handover Deed',
        'aset' => 'Asset',
        'aset_nilai' => ':nama (inventory no. :nup)',
        'tim_asal' => 'Origin Team',
        'tim_tujuan' => 'Destination Team',
        'disahkan_pada' => 'Ratified On',
        'disahkan_oleh' => 'Ratified By',
        'status' => 'Status',
        'status_selesai' => 'Administratively complete (confirmed by the recipient)',
        'status_menunggu' => 'Awaiting confirmation from the recipient',
    ],

    'pengesah_bawaan' => 'Head of the General Affairs Sub-Division',

];
