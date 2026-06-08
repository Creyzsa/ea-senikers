<?php

declare(strict_types=1);

/**
 * Preferensi panel "Pengaturan toko" disimpan di berkas JSON lokal (bukan tabel baru).
 */

function admin_pengaturan_simpan_ke_file(): string
{
    return __DIR__ . '/../pengaturan_toko_admin.json';
}

/**
 * Normalisasi nomor WhatsApp ke E.164 tanpa tanda + (hanya digit).
 */
function admin_pengaturan_normalisasi_wa(string $masukan): string
{
    return preg_replace('/\D+/', '', $masukan) ?? '';
}

/**
 * Format nomor WhatsApp untuk tampilan: +62 822-5934-3380.
 */
function admin_pengaturan_format_wa(string $masukan): string
{
    $d = admin_pengaturan_normalisasi_wa($masukan);
    if ($d === '') {
        return '';
    }
    if (strncmp($d, '62', 2) === 0 && strlen($d) >= 11) {
        $rest = substr($d, 2);
        $len = strlen($rest);
        if ($len === 11) {
            return '+62 ' . substr($rest, 0, 3) . '-' . substr($rest, 3, 4) . '-' . substr($rest, 7, 4);
        }
        if ($len === 10) {
            return '+62 ' . substr($rest, 0, 3) . '-' . substr($rest, 3, 4) . '-' . substr($rest, 7, 3);
        }
        if ($len === 12) {
            return '+62 ' . substr($rest, 0, 4) . '-' . substr($rest, 4, 4) . '-' . substr($rest, 8, 4);
        }
    }
    return '+' . $d;
}

/**
 * @return array{
 *   nama_toko:string,
 *   email_toko:string,
 *   telepon_toko:string,
 *   alamat_toko:string,
 *   metode_pembayaran:string,
 *   biaya_pengiriman:int,
 *   nomor_wa_1:string,
 *   nomor_wa_2:string,
 *   rajaongkir_api_key:string,
 *   rajaongkir_kota_asal_nama:string,
 *   rajaongkir_kota_asal_kode:string,
 *   rajaongkir_kota_asal_id:int,
 *   pakasir_mode:string,
 *   pakasir_project_slug:string,
 *   pakasir_api_key:string,
 *   pakasir_metode_default:string
 * }
 */
function admin_pengaturan_muat_terapan(): array
{
    $bawaan = [
        'nama_toko' => 'EA SENIKERS',
        'email_toko' => 'info@easenikers.com',
        'telepon_toko' => '',
        'alamat_toko' => '',
        'metode_pembayaran' => 'transfer',
        'biaya_pengiriman' => 25000,
        'nomor_wa_1' => '6282259343380',
        'nomor_wa_2' => '6282171590759',
        'rajaongkir_api_key' => '',
        'rajaongkir_kota_asal_nama' => '',
        'rajaongkir_kota_asal_kode' => '',
        'rajaongkir_kota_asal_id' => 0,
        'pakasir_mode' => 'sandbox',
        'pakasir_project_slug' => '',
        'pakasir_api_key' => '',
        'pakasir_metode_default' => 'qris',
    ];

    $path = admin_pengaturan_simpan_ke_file();

    $out = [];
    if (is_file($path)) {
        try {
            $raw = json_decode(file_get_contents($path) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (is_array($raw)) {
                $out = $raw;
            }
        } catch (Throwable $e) {
            $out = [];
        }
    }

    $biaya = (int) ($out['biaya_pengiriman'] ?? $bawaan['biaya_pengiriman']);
    if ($biaya < 0) {
        $biaya = 0;
    }

    $wa1 = admin_pengaturan_normalisasi_wa((string) ($out['nomor_wa_1'] ?? $bawaan['nomor_wa_1']));
    $wa2 = admin_pengaturan_normalisasi_wa((string) ($out['nomor_wa_2'] ?? $bawaan['nomor_wa_2']));

    $kota_asal_kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($out['rajaongkir_kota_asal_kode'] ?? '')) ?? '');
    if (!preg_match('/^[A-Z]{3}\d{5}$/', $kota_asal_kode)) {
        $kota_asal_kode = '';
    }
    $kota_asal_id = (int) ($out['rajaongkir_kota_asal_id'] ?? $bawaan['rajaongkir_kota_asal_id']);
    if ($kota_asal_id < 0) {
        $kota_asal_id = 0;
    }

    // Migrasi kunci Tripay lama (sekali) → Pakasir
    if (empty($out['pakasir_project_slug']) && !empty($out['tripay_merchant_code'])) {
        $out['pakasir_project_slug'] = $out['tripay_merchant_code'];
    }
    if (empty($out['pakasir_api_key']) && !empty($out['tripay_api_key'])) {
        $out['pakasir_api_key'] = $out['tripay_api_key'];
    }
    if (empty($out['pakasir_mode']) && !empty($out['tripay_mode'])) {
        $out['pakasir_mode'] = $out['tripay_mode'];
    }

    $pakasir_mode = strtolower(trim((string) ($out['pakasir_mode'] ?? $bawaan['pakasir_mode'])));
    if (!in_array($pakasir_mode, ['sandbox', 'production'], true)) {
        $pakasir_mode = 'sandbox';
    }
    $pakasir_metode = strtolower(trim((string) ($out['pakasir_metode_default'] ?? $bawaan['pakasir_metode_default'])));
    $metode_valid = ['all', 'qris', 'bni_va', 'bri_va', 'cimb_niaga_va', 'maybank_va', 'permata_va', 'bnc_va', 'atm_bersama_va', 'sampoerna_va', 'artha_graha_va', 'paypal'];
    if (!in_array($pakasir_metode, $metode_valid, true)) {
        $pakasir_metode = 'all';
    }

    return [
        'nama_toko' => trim((string) ($out['nama_toko'] ?? $bawaan['nama_toko'])) ?: $bawaan['nama_toko'],
        'email_toko' => trim((string) ($out['email_toko'] ?? $bawaan['email_toko'])),
        'telepon_toko' => trim((string) ($out['telepon_toko'] ?? $bawaan['telepon_toko'])),
        'alamat_toko' => trim((string) ($out['alamat_toko'] ?? $bawaan['alamat_toko'])),
        'metode_pembayaran' => trim((string) ($out['metode_pembayaran'] ?? $bawaan['metode_pembayaran']))
            ?: $bawaan['metode_pembayaran'],
        'biaya_pengiriman' => $biaya,
        'nomor_wa_1' => $wa1,
        'nomor_wa_2' => $wa2,
        'rajaongkir_api_key' => trim((string) ($out['rajaongkir_api_key'] ?? '')),
        'rajaongkir_kota_asal_nama' => trim((string) ($out['rajaongkir_kota_asal_nama'] ?? '')),
        'rajaongkir_kota_asal_kode' => $kota_asal_kode,
        'rajaongkir_kota_asal_id' => $kota_asal_id,
        'pakasir_mode' => $pakasir_mode,
        'pakasir_project_slug' => trim((string) ($out['pakasir_project_slug'] ?? '')),
        'pakasir_api_key' => trim((string) ($out['pakasir_api_key'] ?? '')),
        'pakasir_metode_default' => $pakasir_metode,
    ];
}

/**
 * Daftar WhatsApp untuk komponen kontak (e164 digit-only + label tampilan).
 *
 * @return list<array{e164:string,tampil:string}>
 */
function admin_pengaturan_daftar_wa(): array
{
    $cfg = admin_pengaturan_muat_terapan();
    $hasil = [];
    foreach (['nomor_wa_1', 'nomor_wa_2'] as $kunci) {
        $e = admin_pengaturan_normalisasi_wa((string) $cfg[$kunci]);
        if ($e === '') {
            continue;
        }
        $hasil[] = [
            'e164' => $e,
            'tampil' => admin_pengaturan_format_wa($e),
        ];
    }
    return $hasil;
}

/**
 * @param array<string,mixed> $data Diterima dari $_POST, field opsional.
 */
function admin_pengaturan_simpan_terapan(array $data): bool
{
    $dibolehkan = ['transfer', 'cod', 'ewallet'];
    $metode = strtolower(trim((string) ($data['metode_pembayaran'] ?? 'transfer')));
    if (!in_array($metode, $dibolehkan, true)) {
        $metode = 'transfer';
    }

    $biaya_raw = $data['biaya_pengiriman'] ?? 0;
    $biaya = is_numeric($biaya_raw) ? (int) $biaya_raw : 0;
    if ($biaya < 0) {
        $biaya = 0;
    }

    $email = strtolower(trim((string) ($data['email_toko'] ?? '')));

    $kota_asal_kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['rajaongkir_kota_asal_kode'] ?? '')) ?? '');
    if (!preg_match('/^[A-Z]{3}\d{5}$/', $kota_asal_kode)) {
        $kota_asal_kode = '';
    }
    $kota_asal_id_raw = $data['rajaongkir_kota_asal_id'] ?? 0;
    $kota_asal_id = is_numeric($kota_asal_id_raw) ? (int) $kota_asal_id_raw : 0;
    if ($kota_asal_id < 0) {
        $kota_asal_id = 0;
    }

    $pakasir_mode = strtolower(trim((string) ($data['pakasir_mode'] ?? 'sandbox')));
    if (!in_array($pakasir_mode, ['sandbox', 'production'], true)) {
        $pakasir_mode = 'sandbox';
    }
    $pakasir_metode = strtolower(trim((string) ($data['pakasir_metode_default'] ?? 'all')));
    $metode_valid = ['all', 'qris', 'bni_va', 'bri_va', 'cimb_niaga_va', 'maybank_va', 'permata_va', 'bnc_va', 'atm_bersama_va', 'sampoerna_va', 'artha_graha_va', 'paypal'];
    if (!in_array($pakasir_metode, $metode_valid, true)) {
        $pakasir_metode = 'all';
    }

    $payload = [
        'nama_toko' => trim((string) ($data['nama_toko'] ?? '')),
        'email_toko' => $email !== '' ? $email : 'info@easenikers.com',
        'telepon_toko' => trim((string) ($data['telepon_toko'] ?? '')),
        'alamat_toko' => trim((string) ($data['alamat_toko'] ?? '')),
        'metode_pembayaran' => $metode,
        'biaya_pengiriman' => $biaya,
        'nomor_wa_1' => admin_pengaturan_normalisasi_wa((string) ($data['nomor_wa_1'] ?? '')),
        'nomor_wa_2' => admin_pengaturan_normalisasi_wa((string) ($data['nomor_wa_2'] ?? '')),
        'rajaongkir_api_key' => trim((string) ($data['rajaongkir_api_key'] ?? '')),
        'rajaongkir_kota_asal_nama' => trim((string) ($data['rajaongkir_kota_asal_nama'] ?? '')),
        'rajaongkir_kota_asal_kode' => $kota_asal_kode,
        'rajaongkir_kota_asal_id' => $kota_asal_id,
        'pakasir_mode' => $pakasir_mode,
        'pakasir_project_slug' => trim((string) ($data['pakasir_project_slug'] ?? '')),
        'pakasir_api_key' => trim((string) ($data['pakasir_api_key'] ?? '')),
        'pakasir_metode_default' => $pakasir_metode,
        'updated_at' => gmdate('c'),
    ];

    if ($payload['nama_toko'] === '') {
        $payload['nama_toko'] = 'EA SENIKERS';
    }

    $path = admin_pengaturan_simpan_ke_file();

    try {
        $isi = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (@file_put_contents($path, $isi, LOCK_EX) === false) {
            return false;
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}
