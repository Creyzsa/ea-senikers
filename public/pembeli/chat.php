<?php
require_once __DIR__ . '/../../includes/auth_db/sesi.php';
require_once __DIR__ . '/../../includes/repositori/katalog_produk.php';
require_once __DIR__ . '/../../includes/url_bantu.php';

wajib_sudah_masuk();

$id_pengguna = (int)($_SESSION['id_pengguna'] ?? 0);
$bilah_pembeli_aktif = 'akun';

$id_produk = isset($_GET['produk']) ? trim((string)$_GET['produk']) : '';
$produk = $id_produk ? katalog_ambil_produk_ber_id($id_produk) : null;

$u_detail = $id_produk ? aplikasi_url('detail-produk?id=' . rawurlencode($id_produk)) : aplikasi_url('produk');
$u_akun = aplikasi_url('akun');

$penjual_id = ambil_penjual_id() ?? 0; // fallback, jika 0 error nanti

// Handle kirim pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['to_id'])) {
    $msg = trim((string)$_POST['message']);
    $to = (int)$_POST['to_id'];
    if ($msg !== '' && $to > 0) {
        chat_kirim($id_pengguna, $to, $msg, $id_produk ?: null);
        // tandai milik sendiri sudah read
        chat_tandai_dibaca($id_pengguna, $id_produk ?: null);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Ambil riwayat (dibungkus agar tidak fatal jika DB unreachable di local)
$messages = chat_ambil_untuk_user($id_pengguna, $id_produk ?: null, 100);
chat_tandai_dibaca($id_pengguna, $id_produk ?: null); // auto read saat buka

$nama_produk = $produk ? (string)($produk['nama_produk'] ?? 'Produk') : 'Penjual';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat dengan Penjual — EA SENIKERS</title>
    <?php include __DIR__ . '/../../includes/komponen/favicon_merek.php'; ?>

    <link rel="stylesheet" href="../assets/css/beranda-toko.css">
    <link rel="stylesheet" href="../assets/css/katalog-produk.css">
    <style>
        .chat-container { max-width: 720px; margin: 0 auto; padding: 1rem; }
        .chat-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
        .chat-box { background:#fff; border:1px solid var(--color-border); border-radius:8px; height:420px; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:0.6rem; }
        .msg { max-width:75%; padding:0.5rem 0.75rem; border-radius:12px; font-size:0.9rem; line-height:1.35; }
        .msg.from-me { align-self:flex-end; background:var(--accent); color:#fff; border-bottom-right-radius:4px; }
        .msg.from-them { align-self:flex-start; background:#f3f4f6; color:#111; border-bottom-left-radius:4px; }
        .msg small { display:block; opacity:0.7; font-size:0.7rem; margin-top:0.2rem; }
        .chat-form { display:flex; gap:0.5rem; margin-top:0.75rem; }
        .chat-form textarea { flex:1; min-height:48px; resize:vertical; }
        .chat-form button { align-self:flex-end; }
        .no-chat { color:var(--color-text-muted); text-align:center; padding:2rem; }
    </style>
</head>
<body class="halaman-toko">

<?php include __DIR__ . '/../../includes/bilah_pembeli.php'; ?>

<div class="kontainer-toko">
    <div class="chat-container">
        <div class="chat-header">
            <div>
                <a class="tautan-kembali" href="<?php echo htmlspecialchars($u_detail, ENT_QUOTES, 'UTF-8'); ?>">← Kembali</a>
                <h1 style="margin:0.2rem 0 0; font-size:1.1rem;">Chat dengan Penjual</h1>
                <?php if ($produk): ?>
                    <small style="color:var(--color-text-muted);"><?= htmlspecialchars($nama_produk) ?></small>
                <?php endif; ?>
            </div>
            <a href="<?php echo htmlspecialchars($u_akun, ENT_QUOTES, 'UTF-8'); ?>" style="font-size:0.85rem;">Akun Saya</a>
        </div>

        <div class="chat-box" id="chat-box">
            <?php if (empty($messages)): ?>
                <div class="no-chat">
                    Belum ada pesan. Mulai chat dengan penjual tentang produk ini.
                </div>
            <?php else: ?>
                <?php foreach ($messages as $m): 
                    $is_me = (int)$m['from_user_id'] === $id_pengguna;
                    $nama = $is_me ? 'Anda' : htmlspecialchars($m['from_nama'] ?? 'Penjual');
                ?>
                    <div class="msg <?= $is_me ? 'from-me' : 'from-them' ?>">
                        <div><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <small><?= $nama ?> • <?= date('d M H:i', strtotime($m['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($penjual_id > 0): ?>
        <form method="post" class="chat-form">
            <input type="hidden" name="to_id" value="<?= (int)$penjual_id ?>">
            <textarea name="message" placeholder="Tulis pesan Anda..." required></textarea>
            <button type="submit" class="tombol-oranye-besar" style="padding:0.5rem 1rem; font-size:0.85rem;">Kirim</button>
        </form>
        <p style="font-size:0.75rem; color:var(--color-text-muted); margin-top:0.4rem;">Chat ini akan dibaca oleh tim penjual EA SENIKERS.</p>
        <?php else: ?>
            <p class="no-chat">Maaf, saat ini chat dengan penjual belum tersedia.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// scroll to bottom
const box = document.getElementById('chat-box');
if (box) box.scrollTop = box.scrollHeight;
</script>

</body>
</html>
