<?php
/**
 * Halaman landas setelah klik tautan di email (konfirmasi daftar atau reset sandi).
 * — Alur A: token di #hash (implicit) → POST ke sesi_konfirmasi / proses_reset_email.
 * — Alur B: ?token_hash=…&type=… (disarankan di template email Supabase) → verifikasi POST
 *   ke Supabase dari server, agar pemindai email tidak memakai tautan verify GET lebih dulu.
 */
require_once __DIR__ . '/../../includes/url_bantu.php';

$aksi_konfirmasi = aplikasi_url('login/sesi_konfirmasi.php');
$aksi_reset_sandi = aplikasi_url('login/proses_reset_email.php');
$aksi_verifikasi_hash = aplikasi_url('login/proses_verifikasi_token_hash.php');
$masuk = aplikasi_url('login/masuk.php');
$lupa = aplikasi_url('login/lupa_sandi.php');

$token_hash_q = isset($_GET['token_hash']) ? trim((string) $_GET['token_hash']) : '';
$type_q = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';
$tipe_hash_izin = ['recovery', 'signup', 'email', 'invite', 'magiclink'];
$tampil_form_token_hash = $token_hash_q !== '' && in_array($type_q, $tipe_hash_izin, true);

$verify_gagal = isset($_GET['verify']) && $_GET['verify'] === 'gagal';
$alasan_verify = isset($_GET['reason']) ? trim((string) $_GET['reason']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mengonfirmasi akun — EA SENIKERS</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 1rem; text-align: center; color: #222; }
        a { color: #27ae60; }
        .kotak { text-align: left; background: #f8f9fa; border-radius: 8px; padding: 1rem 1.1rem; margin-top: 1rem; font-size: 0.95rem; line-height: 1.45; }
        .tombol {
            display: inline-block; margin-top: 1rem; padding: 0.65rem 1.2rem;
            background: #27ae60; color: #fff; border: none; border-radius: 6px;
            font-size: 1rem; cursor: pointer; font-weight: 600;
        }
        .tombol:hover { filter: brightness(0.95); }
        .pesan-api { color: #c0392b; font-size: 0.9rem; margin-top: 0.75rem; }
    </style>
</head>
<body>
<?php if ($tampil_form_token_hash): ?>
    <h1 style="font-size:1.15rem;">Lanjutkan dari email</h1>
    <p>Klik tombol di bawah untuk memverifikasi tautan. Langkah ini mencegah layanan email memakai tautan sebelum Anda (penyebab umum pesan &quot;link kedaluwarsa&quot;).</p>
    <form method="post" action="<?php echo htmlspecialchars($aksi_verifikasi_hash, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="token_hash" value="<?php echo htmlspecialchars($token_hash_q, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_q, ENT_QUOTES, 'UTF-8'); ?>">
        <button class="tombol" type="submit"><?php echo $type_q === 'recovery' ? 'Lanjutkan reset sandi' : 'Verifikasi &amp; lanjutkan'; ?></button>
    </form>
    <p style="margin-top:1.25rem;font-size:0.9rem;"><a href="<?php echo htmlspecialchars($masuk, ENT_QUOTES, 'UTF-8'); ?>">Kembali ke masuk</a></p>
<?php elseif ($verify_gagal): ?>
    <h1 style="font-size:1.15rem;">Verifikasi gagal</h1>
    <p class="pesan-api" role="alert"><?php echo htmlspecialchars($alasan_verify !== '' ? $alasan_verify : 'Tautan tidak berlaku atau sudah kedaluwarsa.', ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="kotak">
        <strong>Saran:</strong> minta tautan baru dari halaman Lupa kata sandi atau Daftar. Jika baru saja minta email, tunggu 1–2 menit sebelum mencoba lagi (batas laju Supabase).
    </div>
    <p style="margin-top:1rem;"><a href="<?php echo htmlspecialchars($lupa, ENT_QUOTES, 'UTF-8'); ?>">Lupa kata sandi</a> · <a href="<?php echo htmlspecialchars($masuk, ENT_QUOTES, 'UTF-8'); ?>">Masuk</a></p>
<?php else: ?>
    <p id="status">Memverifikasi tautan… mohon tunggu.</p>
    <div id="otp-err" style="display:none;text-align:left;"></div>
    <p id="gagal" style="display:none;">Tautan tidak valid atau sudah kedaluwarsa. <a href="<?php echo htmlspecialchars($masuk, ENT_QUOTES, 'UTF-8'); ?>">Kembali ke masuk</a> · <a href="<?php echo htmlspecialchars($lupa, ENT_QUOTES, 'UTF-8'); ?>">Lupa sandi</a></p>

    <form id="form-token" method="post" action="<?php echo htmlspecialchars($aksi_konfirmasi, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;">
        <input type="hidden" name="access_token" id="inp-at" value="">
        <input type="hidden" name="refresh_token" id="inp-rt" value="">
    </form>

    <script>
    (function () {
        var statusEl = document.getElementById('status');
        var gagalEl = document.getElementById('gagal');
        var otpErrEl = document.getElementById('otp-err');
        var form = document.getElementById('form-token');
        var aksiKonfirmasi = <?php echo json_encode($aksi_konfirmasi); ?>;
        var aksiResetSandi = <?php echo json_encode($aksi_reset_sandi); ?>;
        var aksiKode = <?php echo json_encode($aksi_konfirmasi); ?>;

        function muatPayloadJwt(token) {
            try {
                var parts = token.split('.');
                if (parts.length < 2) {
                    return null;
                }
                var base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
                var pad = base64.length % 4;
                if (pad) {
                    base64 += new Array(5 - pad).join('=');
                }
                return JSON.parse(atob(base64));
            } catch (e) {
                return null;
            }
        }

        function tokenAdalahRecovery(access, tipeHash) {
            if (tipeHash === 'recovery') {
                return true;
            }
            var pl = muatPayloadJwt(access);
            if (!pl || !Array.isArray(pl.amr)) {
                return false;
            }
            return pl.amr.some(function (x) {
                if (x === 'recovery') {
                    return true;
                }
                return x && typeof x === 'object' && x.method === 'recovery';
            });
        }

        function tampilkanErrorOtp(params, q) {
            var err = params.get('error') || q.get('error') || '';
            var code = (params.get('error_code') || q.get('error_code') || '').toLowerCase();
            var desc = params.get('error_description') || q.get('error_description') || '';
            try {
                desc = desc ? decodeURIComponent(desc.replace(/\+/g, ' ')) : '';
            } catch (e) {}

            statusEl.style.display = 'none';
            gagalEl.style.display = 'none';
            otpErrEl.style.display = 'block';
            otpErrEl.textContent = '';

            var judul = document.createElement('p');
            judul.style.cssText = 'color:#c0392b;margin:0 0 0.75rem 0;font-weight:bold;';
            judul.textContent = 'Tautan dari email tidak bisa dipakai';
            otpErrEl.appendChild(judul);

            if (desc) {
                var pDesc = document.createElement('p');
                pDesc.style.cssText = 'margin:0 0 0.75rem 0;font-size:0.92rem;';
                pDesc.textContent = desc;
                otpErrEl.appendChild(pDesc);
            }

            if (code === 'otp_expired' || err === 'access_denied') {
                var kotak = document.createElement('div');
                kotak.className = 'kotak';
                kotak.style.marginTop = '0.5rem';
                kotak.innerHTML = '<p style="margin:0 0 0.5rem 0;"><strong>Penyebab umum:</strong> (1) Gmail/Outlook memeriksa tautan otomatis sebelum Anda klik. (2) Anda menunggu layar &quot;Memverifikasi&quot; lalu <strong>mengklik tautan yang sama lagi</strong> — klik pertama sebenarnya sudah memakai token; klik kedua pasti gagal.</p>' +
                    '<p style="margin:0 0 0.5rem 0;"><strong>Yang bisa dilakukan:</strong></p><ul style="margin:0;padding-left:1.2rem;">' +
                    '<li>Minta tautan <strong>baru</strong> sekali, buka dari browser (gmail.com), lalu <strong>tunggu</strong> sampai halaman ganti sandi terbuka (bisa 30–90 detik di WiFi) tanpa klik ulang tautan email.</li>' +
                    '<li>Lebih aman: ubah template email Supabase ke tautan <code>token_hash</code> — lihat komentar di <code>includes/supabase_auth.php</code> pada fungsi <code>supabase_auth_verifikasi_token_hash</code>.</li></ul>';
                otpErrEl.appendChild(kotak);
            }

            var pAksi = document.createElement('p');
            pAksi.style.marginTop = '1rem';
            pAksi.innerHTML = '<a href="<?php echo htmlspecialchars($lupa, ENT_QUOTES, 'UTF-8'); ?>">Minta tautan reset baru</a> · <a href="<?php echo htmlspecialchars($masuk, ENT_QUOTES, 'UTF-8'); ?>">Masuk</a>';
            otpErrEl.appendChild(pAksi);
        }

        var hash = window.location.hash;
        if (hash && hash.charAt(0) === '#') {
            hash = hash.slice(1);
        }
        var params = new URLSearchParams(hash);
        var q = new URLSearchParams(window.location.search);

        if (params.get('error') || q.get('error')) {
            tampilkanErrorOtp(params, q);
            return;
        }

        var access = params.get('access_token') || q.get('access_token');
        var refresh = params.get('refresh_token') || q.get('refresh_token') || '';
        var tipe = (params.get('type') || q.get('type') || '').toLowerCase();

        if (access) {
            document.getElementById('inp-at').value = access;
            document.getElementById('inp-rt').value = refresh;
            var actionUrl = tokenAdalahRecovery(access, tipe) ? aksiResetSandi : aksiKonfirmasi;

            statusEl.innerHTML = '';
            var baris1 = document.createElement('div');
            baris1.style.fontWeight = '600';
            baris1.textContent = 'Menyambungkan ke server…';
            statusEl.appendChild(baris1);
            var baris2 = document.createElement('div');
            baris2.style.cssText = 'font-size:0.88rem;margin-top:0.65rem;line-height:1.45;text-align:left;';
            baris2.innerHTML = 'Tunggu sampai halaman berikutnya terbuka (sering <strong>30–90 detik</strong> lewat WiFi ke PC Anda).<br><br><strong>Jangan</strong> menutup halaman ini dan <strong>jangan klik tautan di Gmail lagi</strong> — klik kedua membuat tautan &quot;kedaluwarsa&quot; walaupun yang pertama masih diproses.';
            statusEl.appendChild(baris2);

            var fd = new FormData();
            fd.append('access_token', access);
            fd.append('refresh_token', refresh);

            fetch(actionUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                redirect: 'follow'
            }).then(function (r) {
                if (r.url) {
                    window.location.replace(r.url);
                    return;
                }
                statusEl.style.display = 'none';
                gagalEl.style.display = 'block';
            }).catch(function () {
                statusEl.style.display = 'block';
                statusEl.textContent = '';
                var e1 = document.createElement('div');
                e1.style.fontWeight = '600';
                e1.style.color = '#c0392b';
                e1.textContent = 'Sambungan putus atau server tidak menjawab';
                statusEl.appendChild(e1);
                var e2 = document.createElement('div');
                e2.style.cssText = 'font-size:0.88rem;margin-top:0.6rem;text-align:left;';
                e2.innerHTML = 'Periksa sambungan internet lalu muat ulang halaman ini. Jika masalah tetap ada, kirim ulang tautan dari email atau hubungi dukungan.';
                statusEl.appendChild(e2);
            });
            return;
        }

        if (q.get('code')) {
            statusEl.textContent = 'Memproses kode…';
            window.location.href = aksiKode + '?code=' + encodeURIComponent(q.get('code'));
            return;
        }

        statusEl.style.display = 'none';
        gagalEl.style.display = 'block';
    })();
    </script>
<?php endif; ?>
</body>
</html>
