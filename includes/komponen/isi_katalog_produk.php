<?php

declare(strict_types=1);

/**
 * Isi #hasil-katalog — dipakai halaman produk & live search (fetch partial).
 * Variabel wajib: $daftar_produk, $daftar_tersaring, $daftar_tersaring_hal, $pg,
 * $q, $brand_filter, $kondisi_filter, $sort, $total_tersaring, $pg_params,
 * $sudah_login, $wishlist_ids, $u_masuk, produk_url_filter()
 */
if (!function_exists('produk_url_filter')) {
    throw new RuntimeException('produk_url_filter tidak tersedia.');
}

?>
<?php if ($daftar_produk === []): ?>
    <div class="katalog-kosong">
        <strong>Katalog kosong atau tidak dapat dimuat.</strong>
        Silakan refresh halaman. Jika masalah berlanjut, hubungi admin toko.
    </div>
<?php elseif ($daftar_tersaring === []): ?>
    <div class="katalog-toolbar">
        <p class="katalog-toolbar__hitung">0 Produk</p>
    </div>
    <div class="katalog-kosong">
        <strong>Produk tidak ditemukan.</strong>
        Coba kata kunci lain atau reset filter.
        <p class="katalog-kosong__aksi">
            <a href="<?php echo htmlspecialchars(aplikasi_url('produk'), ENT_QUOTES, 'UTF-8'); ?>">Tampilkan semua produk</a>
        </p>
    </div>
<?php else: ?>
    <div class="katalog-toolbar">
        <p class="katalog-toolbar__hitung">
            <strong><?php echo (string) $total_tersaring; ?></strong>
            <?php echo $total_tersaring === 1 ? 'Produk' : 'Produk'; ?>
        </p>
        <?php if ($q !== '' || $brand_filter !== '' || $kondisi_filter !== ''): ?>
        <div class="katalog-chip-row" aria-label="Filter aktif">
            <?php if ($q !== ''): ?>
                <a href="<?php echo htmlspecialchars(produk_url_filter(['brand' => $brand_filter, 'kondisi' => $kondisi_filter, 'sort' => $sort]), ENT_QUOTES, 'UTF-8'); ?>">Cari: <?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endif; ?>
            <?php if ($brand_filter !== ''): ?>
                <a href="<?php echo htmlspecialchars(produk_url_filter(['q' => $q, 'kondisi' => $kondisi_filter, 'sort' => $sort]), ENT_QUOTES, 'UTF-8'); ?>">Merek: <?php echo htmlspecialchars($brand_filter, ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endif; ?>
            <?php if ($kondisi_filter !== ''): ?>
                <a href="<?php echo htmlspecialchars(produk_url_filter(['q' => $q, 'brand' => $brand_filter, 'sort' => $sort]), ENT_QUOTES, 'UTF-8'); ?>">Kondisi: <?php echo htmlspecialchars(kondisi_label_pembeli($kondisi_filter), ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="katalog-grid-premium" data-katalog-grid>
        <?php foreach ($daftar_tersaring_hal as $p):
            katalog_render_kartu_produk($p, $sudah_login, $u_masuk, $wishlist_ids);
        endforeach; ?>
    </div>
    <div class="katalog-muat-wrap"
         data-katalog-muat
         data-halaman="<?php echo (int) $pg['halaman']; ?>"
         data-total-hal="<?php echo (int) $pg['total_halaman']; ?>"
         data-base-url="<?php echo htmlspecialchars(aplikasi_url('produk'), ENT_QUOTES, 'UTF-8'); ?>"
         data-params="<?php echo htmlspecialchars(json_encode($pg_params, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ((int) $pg['halaman'] < (int) $pg['total_halaman']): ?>
            <button type="button" class="katalog-muat-lagi">Muat Lebih Banyak</button>
        <?php endif; ?>
    </div>
<?php endif; ?>