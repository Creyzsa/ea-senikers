<?php

declare(strict_types=1);

/**
 * Kontak & lokasi toko EA SENIKERS — satu sumber untuk footer beranda pembeli.
 * Sesuaikan URL peta, WhatsApp (angka E.164 tanpa +), dan akun sosial di sini.
 */
return [
    /** Tautan lengkap ke lokasi di Google Maps */
    'url_peta' => 'https://www.google.com/maps',
    /** Satu atau beberapa baris alamat / petunjuk singkat di bawah tautan Maps */
    'teks_peta' => '',
    /**
     * Daftar WhatsApp: e164 hanya digit negara + nomor (contoh Indonesia: 62812…).
     * @var list<array{e164: string, tampil: string}>
     */
    'wa' => [],
    'sosial' => [
        'instagram' => 'easenikers',
        'tiktok' => 'easenikers',
    ],
];
