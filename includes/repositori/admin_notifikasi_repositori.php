<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth_db/database.php';
require_once __DIR__ . '/../integrasi/notifikasi_telegram.php';
require_once __DIR__ . '/../integrasi/notifikasi_email_smtp.php';

/** PDO pgsql mengirim false sebagai "" — PostgreSQL menolak untuk kolom BOOLEAN. */
function admin_notifikasi_bool_db(bool $nilai): string
{
    return $nilai ? 'true' : 'false';
}

/**
 * @return array{
 *   telegram_bot_token: string,
 *   telegram_chat_id: string,
 *   telegram_aktif: bool,
 *   smtp_host: string,
 *   smtp_port: int,
 *   smtp_user: string,
 *   smtp_pass: string,
 *   smtp_from: string,
 *   smtp_to: string,
 *   email_aktif: bool,
 *   notif_browser_aktif: bool
 * }
 */
function admin_notifikasi_muat_pengaturan(): array
{
    $bawaan = [
        'telegram_bot_token' => trim((string) (getenv('TELEGRAM_BOT_TOKEN') ?: '')),
        'telegram_chat_id' => trim((string) (getenv('TELEGRAM_CHAT_ID') ?: '')),
        'telegram_aktif' => false,
        'smtp_host' => trim((string) (getenv('SMTP_HOST') ?: '')),
        'smtp_port' => (int) (getenv('SMTP_PORT') ?: 587),
        'smtp_user' => trim((string) (getenv('SMTP_USER') ?: '')),
        'smtp_pass' => trim((string) (getenv('SMTP_PASS') ?: '')),
        'smtp_from' => trim((string) (getenv('SMTP_FROM') ?: (defined('EMAIL_PENGIRIM') ? (string) EMAIL_PENGIRIM : ''))),
        'smtp_to' => trim((string) (getenv('SMTP_TO') ?: '')),
        'email_aktif' => false,
        'notif_browser_aktif' => true,
    ];

    if ($bawaan['smtp_port'] <= 0) {
        $bawaan['smtp_port'] = 587;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->query('SELECT * FROM admin_notifikasi_pengaturan WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $bawaan;
        }

        $token_db = trim((string) ($row['telegram_bot_token'] ?? ''));
        if ($token_db !== '') {
            $bawaan['telegram_bot_token'] = $token_db;
        }
        $chat_db = trim((string) ($row['telegram_chat_id'] ?? ''));
        if ($chat_db !== '') {
            $bawaan['telegram_chat_id'] = $chat_db;
        }
        $bawaan['telegram_aktif'] = (bool) ($row['telegram_aktif'] ?? false);

        foreach (['smtp_host', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_to'] as $k) {
            $v = trim((string) ($row[$k] ?? ''));
            if ($v !== '') {
                $bawaan[$k] = $v;
            }
        }
        $port_db = (int) ($row['smtp_port'] ?? 0);
        if ($port_db > 0) {
            $bawaan['smtp_port'] = $port_db;
        }
        $bawaan['email_aktif'] = (bool) ($row['email_aktif'] ?? false);
        $bawaan['notif_browser_aktif'] = (bool) ($row['notif_browser_aktif'] ?? true);
    } catch (Throwable $e) {
        // Tabel belum ada — pakai env/default
    }

    return $bawaan;
}

/**
 * @param array<string, mixed> $data
 */
function admin_notifikasi_simpan_pengaturan(array $data): bool
{
    $port = (int) ($data['smtp_port'] ?? 587);
    if ($port <= 0) {
        $port = 587;
    }

    $payload = [
        'telegram_bot_token' => trim((string) ($data['telegram_bot_token'] ?? '')),
        'telegram_chat_id' => trim((string) ($data['telegram_chat_id'] ?? '')),
        'telegram_aktif' => !empty($data['telegram_aktif']),
        'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
        'smtp_port' => $port,
        'smtp_user' => trim((string) ($data['smtp_user'] ?? '')),
        'smtp_pass' => trim((string) ($data['smtp_pass'] ?? '')),
        'smtp_from' => trim((string) ($data['smtp_from'] ?? '')),
        'smtp_to' => trim((string) ($data['smtp_to'] ?? '')),
        'email_aktif' => !empty($data['email_aktif']),
        'notif_browser_aktif' => !empty($data['notif_browser_aktif']),
    ];

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_notifikasi_pengaturan (
                id, telegram_bot_token, telegram_chat_id, telegram_aktif,
                smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from, smtp_to,
                email_aktif, notif_browser_aktif, updated_at
             ) VALUES (
                1, :tt, :tc, :ta, :sh, :sp, :su, :spw, :sf, :st, :ea, :ba, NOW()
             )
             ON CONFLICT (id) DO UPDATE SET
                telegram_bot_token = EXCLUDED.telegram_bot_token,
                telegram_chat_id = EXCLUDED.telegram_chat_id,
                telegram_aktif = EXCLUDED.telegram_aktif,
                smtp_host = EXCLUDED.smtp_host,
                smtp_port = EXCLUDED.smtp_port,
                smtp_user = EXCLUDED.smtp_user,
                smtp_pass = EXCLUDED.smtp_pass,
                smtp_from = EXCLUDED.smtp_from,
                smtp_to = EXCLUDED.smtp_to,
                email_aktif = EXCLUDED.email_aktif,
                notif_browser_aktif = EXCLUDED.notif_browser_aktif,
                updated_at = NOW()'
        );
        $stmt->execute([
            'tt' => $payload['telegram_bot_token'],
            'tc' => $payload['telegram_chat_id'],
            'ta' => admin_notifikasi_bool_db((bool) $payload['telegram_aktif']),
            'sh' => $payload['smtp_host'],
            'sp' => $payload['smtp_port'],
            'su' => $payload['smtp_user'],
            'spw' => $payload['smtp_pass'],
            'sf' => $payload['smtp_from'],
            'st' => $payload['smtp_to'],
            'ea' => admin_notifikasi_bool_db((bool) $payload['email_aktif']),
            'ba' => admin_notifikasi_bool_db((bool) $payload['notif_browser_aktif']),
        ]);

        return true;
    } catch (Throwable $e) {
        $rls = database_pesan_error_rls($e);
        $detail = trim($e->getMessage());
        $pesan = $rls
            ?? ($detail !== ''
                ? 'Gagal menyimpan pengaturan notifikasi: ' . $detail
                : 'Gagal menyimpan pengaturan notifikasi. Jalankan database/migrations/tahap10_admin_notifikasi.sql di Supabase.');

        throw new RuntimeException($pesan, 0, $e);
    }
}

/**
 * Dipanggil saat pesanan berubah ke status paid.
 */
function admin_notifikasi_pembayaran_masuk(int $order_id): void
{
    if ($order_id <= 0) {
        return;
    }

    $detail = admin_notifikasi_ambil_ringkasan_pesanan($order_id);
    if ($detail === null) {
        return;
    }

    $event_id = admin_notifikasi_catat_event($detail);
    if ($event_id <= 0) {
        return;
    }

    $cfg = admin_notifikasi_muat_pengaturan();
    $pesan = admin_notifikasi_format_pesan_pembayaran($detail, false);
    $pesan_html = admin_notifikasi_format_pesan_pembayaran($detail, true);

    if ($cfg['telegram_aktif']) {
        $hasil = notifikasi_telegram_kirim(
            $cfg['telegram_bot_token'],
            $cfg['telegram_chat_id'],
            $pesan_html,
            true
        );
        if (!$hasil['ok']) {
            error_log('[notifikasi_telegram] ' . $hasil['pesan']);
        }
    }

    if ($cfg['email_aktif']) {
        $subjek = '[EA SENIKERS] Pembayaran masuk — Pesanan #' . $order_id;
        $hasil = notifikasi_email_smtp_kirim(
            $cfg['smtp_host'],
            $cfg['smtp_port'],
            $cfg['smtp_user'],
            $cfg['smtp_pass'],
            $cfg['smtp_from'] !== '' ? $cfg['smtp_from'] : (defined('EMAIL_PENGIRIM') ? (string) EMAIL_PENGIRIM : 'EA SENIKERS <noreply@easenikers.shop>'),
            $cfg['smtp_to'],
            $subjek,
            $pesan
        );
        if (!$hasil['ok']) {
            error_log('[notifikasi_email] ' . $hasil['pesan']);
        }
    }
}

/**
 * @return array{order_id: int, total_price: int, payment_method: string, customer_name: string}|null
 */
function admin_notifikasi_ambil_ringkasan_pesanan(int $order_id): ?array
{
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT o.id, o.total_price, o.payment_method, COALESCE(u.username, \'\') AS customer_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $order_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'order_id' => (int) ($row['id'] ?? 0),
            'total_price' => (int) ($row['total_price'] ?? 0),
            'payment_method' => trim((string) ($row['payment_method'] ?? '')),
            'customer_name' => trim((string) ($row['customer_name'] ?? '')),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @param array{order_id: int, total_price: int, payment_method: string, customer_name: string} $detail
 */
function admin_notifikasi_catat_event(array $detail): int
{
    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_pembayaran_notifikasi (order_id, total_price, payment_method, customer_name)
             VALUES (:oid, :total, :metode, :nama)
             ON CONFLICT (order_id) DO NOTHING
             RETURNING id'
        );
        $stmt->execute([
            'oid' => (int) $detail['order_id'],
            'total' => (int) $detail['total_price'],
            'metode' => (string) $detail['payment_method'],
            'nama' => (string) $detail['customer_name'],
        ]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        $cek = $pdo->prepare('SELECT id FROM admin_pembayaran_notifikasi WHERE order_id = :oid LIMIT 1');
        $cek->execute(['oid' => (int) $detail['order_id']]);

        return (int) ($cek->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('[admin_notifikasi_catat_event] ' . $e->getMessage());

        return 0;
    }
}

/**
 * @param array{order_id: int, total_price: int, payment_method: string, customer_name: string} $detail
 */
function admin_notifikasi_format_pesan_pembayaran(array $detail, bool $html): string
{
    $order_id = (int) $detail['order_id'];
    $total = number_format((int) $detail['total_price'], 0, ',', '.');
    $metode = (string) $detail['payment_method'];
    $nama = (string) $detail['customer_name'];
    $url = aplikasi_url('admin/detail_pesanan_admin.php?id=' . $order_id);

    if ($html) {
        $baris = [
            '<b>Pembayaran masuk</b>',
            'Pesanan #' . $order_id,
            'Total: Rp ' . $total,
        ];
        if ($metode !== '') {
            $baris[] = 'Metode: ' . htmlspecialchars($metode, ENT_QUOTES, 'UTF-8');
        }
        if ($nama !== '') {
            $baris[] = 'Pembeli: ' . htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
        }
        $baris[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Buka detail pesanan</a>';

        return implode("\n", $baris);
    }

    $baris = [
        'Pembayaran masuk',
        'Pesanan #' . $order_id,
        'Total: Rp ' . $total,
    ];
    if ($metode !== '') {
        $baris[] = 'Metode: ' . $metode;
    }
    if ($nama !== '') {
        $baris[] = 'Pembeli: ' . $nama;
    }
    $baris[] = 'Detail: ' . $url;

    return implode("\n", $baris);
}

/**
 * @return array{events: list<array<string, mixed>>, max_id: int, browser_aktif: bool}
 */
function admin_notifikasi_poll(int $since_id): array
{
    $since_id = max(0, $since_id);
    $cfg = admin_notifikasi_muat_pengaturan();

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'SELECT id, order_id, total_price, payment_method, customer_name, created_at
             FROM admin_pembayaran_notifikasi
             WHERE id > :since
             ORDER BY id ASC
             LIMIT 20'
        );
        $stmt->execute(['since' => $since_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $events = [];
        $max_id = $since_id;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $max_id = max($max_id, $id);
            $events[] = [
                'id' => $id,
                'order_id' => (int) ($row['order_id'] ?? 0),
                'total_price' => (int) ($row['total_price'] ?? 0),
                'payment_method' => (string) ($row['payment_method'] ?? ''),
                'customer_name' => (string) ($row['customer_name'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'url' => aplikasi_url('admin/detail_pesanan_admin.php?id=' . (int) ($row['order_id'] ?? 0)),
            ];
        }

        return [
            'events' => $events,
            'max_id' => $max_id,
            'browser_aktif' => (bool) $cfg['notif_browser_aktif'],
        ];
    } catch (Throwable $e) {
        return ['events' => [], 'max_id' => $since_id, 'browser_aktif' => (bool) $cfg['notif_browser_aktif']];
    }
}

/**
 * @return array{ok: bool, pesan: string}
 */
function admin_notifikasi_tes_telegram(array $cfg): array
{
    $teks = '<b>Tes notifikasi EA SENIKERS</b>' . "\n"
        . 'Koneksi Telegram berhasil pada ' . gmdate('d M Y H:i') . ' UTC.';

    return notifikasi_telegram_kirim(
        (string) ($cfg['telegram_bot_token'] ?? ''),
        (string) ($cfg['telegram_chat_id'] ?? ''),
        $teks,
        true
    );
}

/**
 * @return array{ok: bool, pesan: string}
 */
function admin_notifikasi_tes_email(array $cfg): array
{
    $subjek = '[EA SENIKERS] Tes notifikasi email admin';
    $body = "Ini pesan uji dari panel admin EA SENIKERS.\nWaktu: " . gmdate('c');

    return notifikasi_email_smtp_kirim(
        (string) ($cfg['smtp_host'] ?? ''),
        (int) ($cfg['smtp_port'] ?? 587),
        (string) ($cfg['smtp_user'] ?? ''),
        (string) ($cfg['smtp_pass'] ?? ''),
        (string) (($cfg['smtp_from'] ?? '') !== '' ? $cfg['smtp_from'] : (defined('EMAIL_PENGIRIM') ? (string) EMAIL_PENGIRIM : 'EA SENIKERS <noreply@easenikers.shop>')),
        (string) ($cfg['smtp_to'] ?? ''),
        $subjek,
        $body
    );
}