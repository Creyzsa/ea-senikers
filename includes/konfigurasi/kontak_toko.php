<?php

declare(strict_types=1);

/**
 * Kontak & lokasi toko EA SENIKERS — satu sumber untuk footer beranda pembeli.
 * Daftar WhatsApp dibaca dari pengaturan admin (panel Pengaturan toko) sehingga
 * admin dapat memperbarui nomor layanan tanpa mengubah kode.
 */
require_once __DIR__ . '/../repositori/admin_pengaturan_repositori.php';

return [
    /**
     * Tautan ke listing toko di Google Maps. Memakai nama bisnis + kota agar
     * langsung membuka pin resmi "EA Senikers" (rating, foto, jam buka) — bukan
     * sekadar pencarian alamat.
     */
    'url_peta' => 'https://www.google.com/maps/place/EA+Senikers/@-0.4565269,100.4061786,17z',
    /** Alamat lengkap seperti yang tertera di listing Google Maps. */
    'teks_peta' => 'Jalan Dr Jl. Abu Hanifah, Guguk Malintang, Kec. Padang Panjang Tim., Kota Padang Panjang, Sumatera Barat 27118',
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
