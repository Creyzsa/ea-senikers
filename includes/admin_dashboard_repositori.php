<?php

declare(strict_types=1);

/**
 * Dashboard admin — ringkasan angka, grafik, dan aktivitas dari database + katalog Supabase.
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/pesanan_repositori.php';
require_once __DIR__ . '/katalog_produk.php';

/** Status pesanan yang dihitung sebagai pendapatan tercatat */
function admin_dashboard_status_pendapatan(): array
{
    return ['paid', 'processed', 'shipped', 'completed'];
}

/**
 * Pendapatan tercatat (total_price) dalam rentang setengah tertutup [mulai, akhir].
 *
 * @return float
 */
function admin_dashboard_total_pendapatan_antara(string $mulai_iso, string $akhir_iso): float
{
    if (!pesanan_cek_tabel_ada()) {
        return 0.0;
    }
    try {
        $pdo = koneksi_database();
        $st = admin_dashboard_status_pendapatan();
        $ph = implode(',', array_fill(0, count($st), '?'));
        $sql = "SELECT COALESCE(SUM(total_price), 0)::float AS jumlah
                FROM orders
                WHERE status IN ($ph)
                  AND created_at >= ?
                  AND created_at < ?";
        $stmt = $pdo->prepare($sql);
        $i = 1;
        foreach ($st as $s) {
            $stmt->bindValue($i++, $s, PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, $mulai_iso, PDO::PARAM_STR);
        $stmt->bindValue($i++, $akhir_iso, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        return (float) ($row['jumlah'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * @return array{pendapatan_30:number, pendapatan_30_sebelumnya:number, pesanan_total:int, pesanan_bulan_ini:int, pesanan_pending:int, pengguna:int, produk_total:int, produk_ready:int}
 */
function admin_dashboard_stat_kartu(): array
{
    $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
    $now = new DateTimeImmutable('now', $tz);

    $awal30 = $now->sub(new DateInterval('P30D'));
    $awal60 = $now->sub(new DateInterval('P60D'));

    $pend30 = admin_dashboard_total_pendapatan_antara($awal30->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'));
    $pend30prev = admin_dashboard_total_pendapatan_antara($awal60->format('Y-m-d H:i:s'), $awal30->format('Y-m-d H:i:s'));

    $pesanan_total = 0;
    $pesanan_bulan_ini = 0;
    $pesanan_pending = 0;
    $pengguna = 0;

    if (pesanan_cek_tabel_ada()) {
        try {
            $pdo = koneksi_database();
            $pesanan_total = (int) $pdo->query('SELECT COUNT(*)::int FROM orders')->fetchColumn();
            $pesanan_pending = (int) $pdo->query("SELECT COUNT(*)::int FROM orders WHERE status = 'pending'")->fetchColumn();
            $stmtB = $pdo->prepare(
                'SELECT COUNT(*)::int FROM orders WHERE created_at >= ? AND created_at < ?'
            );
            $stmtB->execute([
                $now->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                $now->modify('first day of next month')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            ]);
            $pesanan_bulan_ini = (int) $stmtB->fetchColumn();
        } catch (Throwable $e) {
            $pesanan_total = $pesanan_bulan_ini = $pesanan_pending = 0;
        }
    }

    try {
        $pdo = koneksi_database();
        $pengguna = (int) $pdo->query('SELECT COUNT(*)::int FROM users')->fetchColumn();
    } catch (Throwable $e) {
        $pengguna = 0;
    }

    $produk_total = 0;
    $produk_ready = 0;
    foreach (katalog_ambil_semua_produk() as $p) {
        ++$produk_total;
        if (!empty($p['siap_jual'])) {
            ++$produk_ready;
        }
    }

    return [
        'pendapatan_30' => $pend30,
        'pendapatan_30_sebelumnya' => $pend30prev,
        'pesanan_total' => $pesanan_total,
        'pesanan_bulan_ini' => $pesanan_bulan_ini,
        'pesanan_pending' => $pesanan_pending,
        'pengguna' => $pengguna,
        'produk_total' => $produk_total,
        'produk_ready' => $produk_ready,
    ];
}

/**
 * 7 hari terakhir termasuk hari ini: label singkat-ID + tinggi batang persen terhadap maksimum.
 *
 * @return list<array{label:string,height_pct:float,nilai:number}>
 */
function admin_dashboard_grafik_mingguan(): array
{
    $tz = date_default_timezone_get() ?: 'UTC';
    $labels_hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    $out = [];
    $now = new DateTimeImmutable('now', new DateTimeZone($tz));

    $byDayVal = [];

    if (pesanan_cek_tabel_ada()) {
        try {
            $pdo = koneksi_database();
            $st = admin_dashboard_status_pendapatan();
            $ph = implode(',', array_fill(0, count($st), '?'));
            $from = $now->sub(new DateInterval('P6D'))->setTime(0, 0, 0)->format('Y-m-d H:i:s');

            $sql = "SELECT created_at, total_price
                    FROM orders
                    WHERE status IN ($ph)
                      AND created_at >= ?";
            $stmt = $pdo->prepare($sql);
            $i = 1;
            foreach ($st as $s) {
                $stmt->bindValue($i++, $s, PDO::PARAM_STR);
            }
            $stmt->bindValue($i++, $from, PDO::PARAM_STR);
            $stmt->execute();

            foreach ($stmt->fetchAll() as $row) {
                $ts = strtotime((string) ($row['created_at'] ?? ''));
                if ($ts === false) {
                    continue;
                }
                $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone($tz));
                $dayKey = $d->format('Y-m-d');
                $byDayVal[$dayKey] = ($byDayVal[$dayKey] ?? 0.0) + (float) ($row['total_price'] ?? 0);
            }
        } catch (Throwable $e) {
            $byDayVal = [];
        }
    }

    $maxNilai = 1.0;
    for ($back = 6; $back >= 0; --$back) {
        $d = $now->sub(new DateInterval('P' . $back . 'D'));
        $key = $d->format('Y-m-d');
        $v = (float) ($byDayVal[$key] ?? 0.0);
        if ($v > $maxNilai) {
            $maxNilai = $v;
        }
    }

    for ($back = 6; $back >= 0; --$back) {
        $d = $now->sub(new DateInterval('P' . $back . 'D'));
        $key = $d->format('Y-m-d');
        $nilai = (float) ($byDayVal[$key] ?? 0.0);

        $w = ((int) $d->format('w'));
        $out[] = [
            'label' => $labels_hari[$w],
            'height_pct' => $maxNilai > 0 ? ($nilai / $maxNilai) * 100.0 : 0.0,
            'nilai' => $nilai,
        ];
    }

    return $out;
}

/**
 * Riwayat singkat gabungan: pesanan + produk baru (urut waktu).
 *
 * @return list<array{jenis:string,teks:string,waktu:string,url?:string,warna:string}>
 */
function admin_dashboard_aktivitas_terbaru(int $batas = 8): array
{
    $batas = max(4, min(20, $batas));
    $event = [];

    try {
        $pdo = koneksi_database();
        $labels = pesanan_status_label_id();
        $stmt = $pdo->query(
            'SELECT o.id, o.status, o.created_at,
                    (SELECT oi.product_name FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) AS produk_awal,
                    u.nama_pengguna
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.created_at DESC NULLS LAST
             LIMIT 12'
        );
        if ($stmt) {
            foreach ($stmt->fetchAll() as $r) {
                $id = (int) ($r['id'] ?? 0);
                $st = (string) ($r['status'] ?? '');
                $nama = trim((string) ($r['nama_pengguna'] ?? ''));
                if ($nama === '') {
                    $nama = 'Pembeli';
                }
                $pr = trim((string) ($r['produk_awal'] ?? ''));
                $lab = $labels[$st] ?? $st;

                $teks = 'Pesanan #' . $id . ' · ' . $lab;
                if ($pr !== '') {
                    $teks .= ' — ' . $pr;
                }

                $ts = strtotime((string) ($r['created_at'] ?? ''));
                $event[] = [
                    '_ts' => $ts !== false ? $ts : 0,
                    'jenis' => 'order',
                    'teks' => $teks,
                    'waktu_rel' => (string) ($r['created_at'] ?? ''),
                    'url' => aplikasi_url('admin/detail_pesanan_admin.php?id=' . $id),
                    'warna' => $st === 'cancelled' ? 'merah' : ($st === 'completed' ? 'hijau' : 'biru'),
                ];
            }
        }
    } catch (Throwable $e) {
    }

    $produk_rows = array_slice(katalog_ambil_semua_produk(), 0, 10);
    foreach ($produk_rows as $p) {
        $nama = (string) ($p['nama_produk'] ?? '');
        $idp = (string) ($p['id_produk'] ?? '');
        if ($nama === '') {
            continue;
        }

        $ts = strtotime((string) ($p['created_at'] ?? ''));
        if ($ts === false) {
            $ts = (int) (microtime(true));
        }

        $event[] = [
            '_ts' => $ts,
            'jenis' => 'product',
            'teks' => 'Produk · ' . $nama,
            'waktu_rel' => (string) ($p['created_at'] ?? ''),
            'url' => $idp !== '' ? aplikasi_url('admin/produk_admin.php?edit=' . rawurlencode($idp)) : null,
            'warna' => 'ungu',
        ];
    }

    usort($event, static fn (array $a, array $b): int => ($b['_ts'] <=> $a['_ts']));
    $event = array_slice($event, 0, $batas);

    $out = [];
    foreach ($event as $e) {
        unset($e['_ts']);
        $iso = trim((string) ($e['waktu_rel'] ?? ''));
        $e['waktu'] = $iso !== '' ? admin_dashboard_format_waktu_relatif($iso) : '—';

        unset($e['waktu_rel']);
        $out[] = $e;
    }

    return $out;
}

/** Label relatif bahasa Indonesia untuk timestamp ISO/strtotime-able */
function admin_dashboard_format_waktu_relatif(string $iso): string
{
    $ts = strtotime($iso);
    if ($ts === false) {
        return '—';
    }
    $selisih = time() - $ts;
    if ($selisih < 60) {
        return 'Baru saja';
    }
    if ($selisih < 3600) {
        return (int) floor($selisih / 60) . ' mnt';
    }
    if ($selisih < 86400) {
        return (int) floor($selisih / 3600) . ' jam';
    }
    if ($selisih < 86400 * 7) {
        return (int) floor($selisih / 86400) . ' hari';
    }

    return date('d/m/Y H:i', $ts);
}

/**
 * Persentase perubahan; aman untuk pembagian nol (devolver null = tidak ada perbandingan).
 */
function admin_dashboard_delta_persen(float $baru, float $lama): ?float
{
    if ($lama <= 0.0 && $baru <= 0.0) {
        return null;
    }

    $dasar = $lama > 0.0 ? $lama : 1e-9;

    return (($baru - $lama) / $dasar) * 100.0;
}
