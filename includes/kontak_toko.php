<?php

declare(strict_types=1);

/**
 * Kontak & lokasi toko EA SENIKERS — satu sumber untuk footer beranda pembeli.
 * Daftar WhatsApp dibaca dari pengaturan admin (panel Pengaturan toko) sehingga
 * admin dapat memperbarui nomor layanan tanpa mengubah kode.
 */
require_once __DIR__ . '/admin_pengaturan_repositori.php';

return [
    /** Tautan lengkap ke lokasi di Google Maps */
    'url_peta' => 'https://www.google.com/maps',
    /** Satu atau beberapa baris alamat / petunjuk singkat di bawah tautan Maps */
    'teks_peta' => '',
    /**
     * Daftar WhatsApp aktif (dari pengaturan admin): e164 hanya digit + label tampil.
     * @var list<array{e164: string, tampil: string}>
     */
    'wa' => admin_pengaturan_daftar_wa(),
    'sosial' => [
        'instagram' => 'easenikers',
        'tiktok' => 'easecondbrandofficial',
    ],
];
