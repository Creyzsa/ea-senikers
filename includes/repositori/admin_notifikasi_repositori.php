<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth_db/database.php';
require_once __DIR__ . '/../integrasi/notifikasi_telegram.php';
require_once __DIR__ . '/../integrasi/notifikasi_email_smtp.php';
require_once __DIR__ . '/../integrasi/notifikasi_web_push.php';

/** PDO pgsql mengirim false sebagai "" — PostgreSQL menolak untuk kolom BOOLEAN. */
function admin_notifikasi_bool_db(bool $nilai): string
{
    return $nilai ? 'true' : 'false';
}

function admin_notifikasi_pesan_migrasi_tahap11(): string
{
    return 'Kolom Web Push belum ada di database. Buka Supabase → SQL Editor, jalankan file database/migrations/tahap11_admin_push.sql, lalu simpan lagi.';
}

/**
 * Cek apakah migrasi tahap11 (push_aktif, vapid_*) sudah dijalankan.
 */
function admin_notifikasi_kolom_push_tersedia(): bool
{
    static $tersedia = null;
    if ($tersedia !== null) {
        return $tersedia;
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'admin_notifikasi_pengaturan'
               AND column_name = 'push_aktif'
             LIMIT 1"
        );
        $stmt->execute();
        $tersedia = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $tersedia = false;
    }

    return $tersedia;
}

/**
 * Isi ulang token/password dari DB bila field password dikosongkan browser saat submit.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function admin_notifikasi_gabung_form_dengan_simpan(array $post): array
{
    $simpan = admin_notifikasi_muat_pengaturan();
    if (trim((string) ($post['telegram_bot_token'] ?? '')) === '') {
        $post['telegram_bot_token'] = $simpan['telegram_bot_token'];
    }
    if (trim((string) ($post['smtp_pass'] ?? '')) === '') {
        $post['smtp_pass'] = $simpan['smtp_pass'];
    }

    return $post;
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
 *   notif_browser_aktif: bool,
 *   push_aktif: bool,
 *   vapid_public_key: string,
 *   vapid_private_key: string
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
        'push_aktif' => false,
        'vapid_public_key' => trim((string) (getenv('VAPID_PUBLIC_KEY') ?: '')),
        'vapid_private_key' => trim((string) (getenv('VAPID_PRIVATE_KEY') ?: '')),
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
        $bawaan['push_aktif'] = (bool) ($row['push_aktif'] ?? false);
        $vapid_pub = trim((string) ($row['vapid_public_key'] ?? ''));
        if ($vapid_pub !== '') {
            $bawaan['vapid_public_key'] = $vapid_pub;
        }
        $vapid_priv = trim((string) ($row['vapid_private_key'] ?? ''));
        if ($vapid_priv !== '') {
            $bawaan['vapid_private_key'] = $vapid_priv;
        }
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
        'push_aktif' => !empty($data['push_aktif']),
        'vapid_public_key' => trim((string) ($data['vapid_public_key'] ?? '')),
        'vapid_private_key' => trim((string) ($data['vapid_private_key'] ?? '')),
    ];

    $simpan_lama = admin_notifikasi_muat_pengaturan();
    if ($payload['vapid_public_key'] === '') {
        $payload['vapid_public_key'] = (string) $simpan_lama['vapid_public_key'];
    }
    if ($payload['vapid_private_key'] === '') {
        $payload['vapid_private_key'] = (string) $simpan_lama['vapid_private_key'];
    }
    $push_siap = admin_notifikasi_kolom_push_tersedia();
    if (!$push_siap && $payload['push_aktif']) {
        throw new RuntimeException(admin_notifikasi_pesan_migrasi_tahap11());
    }

    if ($push_siap && $payload['push_aktif'] && ($payload['vapid_public_key'] === '' || $payload['vapid_private_key'] === '')) {
        $vapid = admin_notifikasi_pastikan_vapid_keys($payload);
        $payload['vapid_public_key'] = $vapid['vapid_public_key'];
        $payload['vapid_private_key'] = $vapid['vapid_private_key'];
    }

    $params_dasar = [
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
    ];

    try {
        $pdo = koneksi_database();
        if ($push_siap) {
            $stmt = $pdo->prepare(
                'INSERT INTO admin_notifikasi_pengaturan (
                    id, telegram_bot_token, telegram_chat_id, telegram_aktif,
                    smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from, smtp_to,
                    email_aktif, notif_browser_aktif, push_aktif, vapid_public_key, vapid_private_key, updated_at
                 ) VALUES (
                    1, :tt, :tc, :ta, :sh, :sp, :su, :spw, :sf, :st, :ea, :ba, :pa, :vpk, :vpv, NOW()
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
                    push_aktif = EXCLUDED.push_aktif,
                    vapid_public_key = EXCLUDED.vapid_public_key,
                    vapid_private_key = EXCLUDED.vapid_private_key,
                    updated_at = NOW()'
            );
            $stmt->execute($params_dasar + [
                'pa' => admin_notifikasi_bool_db((bool) $payload['push_aktif']),
                'vpk' => $payload['vapid_public_key'],
                'vpv' => $payload['vapid_private_key'],
            ]);
        } else {
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
            $stmt->execute($params_dasar);
        }

        return true;
    } catch (Throwable $e) {
        $rls = database_pesan_error_rls($e);
        $detail = trim($e->getMessage());
        if (str_contains($detail, 'push_aktif') || str_contains($detail, '42703')) {
            throw new RuntimeException(admin_notifikasi_pesan_migrasi_tahap11(), 0, $e);
        }
        $pesan = $rls
            ?? ($detail !== ''
                ? 'Gagal menyimpan pengaturan notifikasi: ' . $detail
                : 'Gagal menyimpan pengaturan notifikasi. Jalankan migrasi tahap10 & tahap11 di Supabase.');

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

    if ($cfg['push_aktif']) {
        admin_push_kirim_pembayaran($event_id, $detail);
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
 * Data panel lonceng notifikasi (daftar + jumlah belum dibaca).
 *
 * @return array{
 *   recent: list<array<string, mixed>>,
 *   unread_count: int,
 *   max_id: int
 * }
 */
function admin_notifikasi_panel(int $read_until, int $limit = 20): array
{
    $read_until = max(0, $read_until);
    $limit = max(1, min(50, $limit));

    try {
        $pdo = koneksi_database();

        $max_id = (int) ($pdo->query('SELECT COALESCE(MAX(id), 0) FROM admin_pembayaran_notifikasi')->fetchColumn() ?: 0);

        $stmt_unread = $pdo->prepare(
            'SELECT COUNT(*) FROM admin_pembayaran_notifikasi WHERE id > :read_until'
        );
        $stmt_unread->execute(['read_until' => $read_until]);
        $unread_count = (int) ($stmt_unread->fetchColumn() ?: 0);

        $stmt = $pdo->prepare(
            'SELECT id, order_id, total_price, payment_method, customer_name, created_at
             FROM admin_pembayaran_notifikasi
             ORDER BY id DESC
             LIMIT :lim'
        );
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $recent = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $recent[] = [
                'id' => $id,
                'order_id' => (int) ($row['order_id'] ?? 0),
                'total_price' => (int) ($row['total_price'] ?? 0),
                'payment_method' => (string) ($row['payment_method'] ?? ''),
                'customer_name' => (string) ($row['customer_name'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'url' => aplikasi_url('admin/detail_pesanan_admin.php?id=' . (int) ($row['order_id'] ?? 0)),
                'unread' => $id > $read_until,
            ];
        }

        return [
            'recent' => $recent,
            'unread_count' => $unread_count,
            'max_id' => $max_id,
        ];
    } catch (Throwable $e) {
        return ['recent' => [], 'unread_count' => 0, 'max_id' => $read_until];
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

/**
 * @param array<string, mixed> $cfg
 * @return array{vapid_public_key: string, vapid_private_key: string}
 */
function admin_notifikasi_pastikan_vapid_keys(array $cfg): array
{
    $pub = trim((string) ($cfg['vapid_public_key'] ?? ''));
    $priv = trim((string) ($cfg['vapid_private_key'] ?? ''));
    if ($pub !== '' && $priv !== '') {
        return ['vapid_public_key' => $pub, 'vapid_private_key' => $priv];
    }

    $gen = web_push_generate_vapid_keys();

    return [
        'vapid_public_key' => $gen['public_key'],
        'vapid_private_key' => $gen['private_key_pem'],
    ];
}

function admin_push_vapid_subject(): string
{
    $cfg = admin_notifikasi_muat_pengaturan();
    $to = trim((string) ($cfg['smtp_to'] ?? ''));
    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'mailto:' . $to;
    }

    return 'mailto:admin@easenikers.shop';
}

/**
 * @return array{ok: bool, pesan: string, public_key?: string}
 */
function admin_push_info_vapid(): array
{
    $cfg = admin_notifikasi_muat_pengaturan();
    if (!$cfg['push_aktif']) {
        return ['ok' => false, 'pesan' => 'Push notifikasi tidak aktif.'];
    }

    $vapid = admin_notifikasi_pastikan_vapid_keys($cfg);
    if (trim((string) $cfg['vapid_public_key']) === '' || trim((string) $cfg['vapid_private_key']) === '') {
        try {
            admin_notifikasi_simpan_pengaturan(array_merge($cfg, [
                'push_aktif' => true,
                'vapid_public_key' => $vapid['vapid_public_key'],
                'vapid_private_key' => $vapid['vapid_private_key'],
            ]));
        } catch (Throwable $e) {
            return ['ok' => false, 'pesan' => $e->getMessage()];
        }
    }

    return [
        'ok' => true,
        'pesan' => 'VAPID siap.',
        'public_key' => $vapid['vapid_public_key'],
    ];
}

/**
 * @param array<string, mixed> $subscription
 * @return array{ok: bool, pesan: string}
 */
function admin_push_simpan_langganan(int $user_id, array $subscription): array
{
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $p256dh = trim((string) ($subscription['keys']['p256dh'] ?? $subscription['p256dh'] ?? ''));
    $auth = trim((string) ($subscription['keys']['auth'] ?? $subscription['auth'] ?? ''));
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return ['ok' => false, 'pesan' => 'Data langganan push tidak lengkap.'];
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_push_subscription (user_id, endpoint, p256dh, auth, user_agent, updated_at)
             VALUES (:uid, :ep, :p256, :auth, :ua, NOW())
             ON CONFLICT (endpoint) DO UPDATE SET
                user_id = EXCLUDED.user_id,
                p256dh = EXCLUDED.p256dh,
                auth = EXCLUDED.auth,
                user_agent = EXCLUDED.user_agent,
                updated_at = NOW()'
        );
        $stmt->execute([
            'uid' => max(0, $user_id),
            'ep' => $endpoint,
            'p256' => $p256dh,
            'auth' => $auth,
            'ua' => trim((string) ($subscription['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '')),
        ]);

        return ['ok' => true, 'pesan' => 'Langganan push disimpan.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan langganan push. Jalankan tahap11_admin_push.sql.'];
    }
}

/**
 * @return array{ok: bool, pesan: string}
 */
function admin_push_hapus_langganan(string $endpoint): array
{
    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return ['ok' => false, 'pesan' => 'Endpoint kosong.'];
    }

    try {
        $pdo = koneksi_database();
        $stmt = $pdo->prepare('DELETE FROM admin_push_subscription WHERE endpoint = :ep');
        $stmt->execute(['ep' => $endpoint]);

        return ['ok' => true, 'pesan' => 'Langganan push dihapus.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'pesan' => 'Gagal menghapus langganan push.'];
    }
}

/**
 * @return list<array{endpoint: string, p256dh: string, auth: string}>
 */
function admin_push_muat_langganan_aktif(): array
{
    try {
        $pdo = koneksi_database();
        $rows = $pdo->query(
            'SELECT endpoint, p256dh, auth FROM admin_push_subscription ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'endpoint' => trim((string) ($row['endpoint'] ?? '')),
                'p256dh' => trim((string) ($row['p256dh'] ?? '')),
                'auth' => trim((string) ($row['auth'] ?? '')),
            ];
        }

        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param array{order_id: int, total_price: int, payment_method: string, customer_name: string} $detail
 */
function admin_push_kirim_pembayaran(int $event_id, array $detail): void
{
    $cfg = admin_notifikasi_muat_pengaturan();
    if (!$cfg['push_aktif']) {
        return;
    }

    $vapid = admin_notifikasi_pastikan_vapid_keys($cfg);
    $subs = admin_push_muat_langganan_aktif();
    if ($subs === []) {
        return;
    }

    $order_id = (int) $detail['order_id'];
    $total = number_format((int) $detail['total_price'], 0, ',', '.');
    $nama = trim((string) $detail['customer_name']);
    $body = 'Pesanan #' . $order_id . ' — Rp ' . $total;
    if ($nama !== '') {
        $body .= ' · ' . $nama;
    }

    $payload = json_encode([
        'title' => 'Pembayaran masuk',
        'body' => $body,
        'url' => aplikasi_url('admin/detail_pesanan_admin.php?id=' . $order_id),
        'event_id' => $event_id,
        'order_id' => $order_id,
        'total_price' => (int) $detail['total_price'],
        'customer_name' => $nama,
        'tag' => 'easenikers-paid-' . $order_id,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return;
    }

    $vapid_send = [
        'public_key' => $vapid['vapid_public_key'],
        'private_key_pem' => $vapid['vapid_private_key'],
        'subject' => admin_push_vapid_subject(),
    ];

    foreach ($subs as $sub) {
        $hasil = web_push_kirim_satu($sub, $payload, $vapid_send);
        if (!$hasil['ok']) {
            error_log('[notifikasi_push] ' . $hasil['pesan']);
            if (!empty($hasil['hapus'])) {
                admin_push_hapus_langganan((string) $sub['endpoint']);
            }
        }
    }
}

/**
 * @return array{ok: bool, pesan: string}
 */
function admin_notifikasi_tes_push(): array
{
    $cfg = admin_notifikasi_muat_pengaturan();
    if (!$cfg['push_aktif']) {
        return ['ok' => false, 'pesan' => 'Aktifkan push notifikasi terlebih dahulu.'];
    }

    $subs = admin_push_muat_langganan_aktif();
    if ($subs === []) {
        return ['ok' => false, 'pesan' => 'Belum ada perangkat terdaftar. Klik "Aktifkan push di perangkat ini" dan izinkan notifikasi.'];
    }

    $payload = json_encode([
        'title' => 'Tes push EA SENIKERS',
        'body' => 'Notifikasi push admin berhasil pada ' . gmdate('d M Y H:i') . ' UTC.',
        'url' => aplikasi_url('admin/notifikasi_admin.php'),
        'event_id' => 0,
        'order_id' => 0,
        'tag' => 'easenikers-push-test',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return ['ok' => false, 'pesan' => 'Gagal membuat payload tes.'];
    }

    $vapid = admin_notifikasi_pastikan_vapid_keys($cfg);
    $vapid_send = [
        'public_key' => $vapid['vapid_public_key'],
        'private_key_pem' => $vapid['vapid_private_key'],
        'subject' => admin_push_vapid_subject(),
    ];

    $sukses = 0;
    $gagal = 0;
    $pesan_gagal_terakhir = '';
    foreach ($subs as $sub) {
        $hasil = web_push_kirim_satu($sub, $payload, $vapid_send);
        if ($hasil['ok']) {
            $sukses++;
        } else {
            $gagal++;
            $pesan_gagal_terakhir = (string) ($hasil['pesan'] ?? '');
            if (!empty($hasil['hapus'])) {
                admin_push_hapus_langganan((string) $sub['endpoint']);
            }
        }
    }

    if ($sukses > 0) {
        return ['ok' => true, 'pesan' => 'Tes push terkirim ke ' . $sukses . ' perangkat.' . ($gagal > 0 ? ' (' . $gagal . ' gagal)' : '')];
    }

    $hint = 'Langganan di browser sudah ada, tapi server gagal mengirim. ';
    if ($pesan_gagal_terakhir !== '') {
        $hint .= 'Detail: ' . $pesan_gagal_terakhir . ' ';
    }
    $hint .= 'Klik Nonaktifkan push lalu Aktifkan push lagi di perangkat ini, kemudian Tes Push.';

    return ['ok' => false, 'pesan' => $hint];
}