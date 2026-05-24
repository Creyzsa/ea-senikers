/**
 * Cascading dropdown alamat Indonesia (provinsi → kota → kecamatan).
 * Sumber data: https://www.emsifa.com/api-wilayah-indonesia (BPS, tanpa API key).
 *
 * Cara pakai di HTML:
 *   <select data-cascading="provinsi" data-saved="Jawa Timur" name="provinsi">...</select>
 *   <select data-cascading="kota" data-saved="Surabaya" name="kota">...</select>
 *   <select data-cascading="kecamatan" data-saved="Sukolilo" name="kecamatan">...</select>
 *
 * Saat halaman dimuat:
 *   1. Ambil daftar provinsi → isi dropdown.
 *   2. Bila ada data tersimpan, otomatis pilih + ambil kota → otomatis pilih + ambil kecamatan.
 *
 * Saat user ganti pilihan:
 *   - Ganti provinsi → kosongkan kota & kecamatan, ambil daftar kota baru.
 *   - Ganti kota     → kosongkan kecamatan, ambil daftar kecamatan baru.
 */
(function () {
    'use strict';

    const API_BASE = 'https://www.emsifa.com/api-wilayah-indonesia/api';

    const selProvinsi = document.querySelector('select[data-cascading="provinsi"]');
    const selKota = document.querySelector('select[data-cascading="kota"]');
    const selKecamatan = document.querySelector('select[data-cascading="kecamatan"]');

    if (!selProvinsi || !selKota || !selKecamatan) {
        return;
    }

    const savedProvinsi = (selProvinsi.dataset.saved || '').trim();
    const savedKota = (selKota.dataset.saved || '').trim();
    const savedKecamatan = (selKecamatan.dataset.saved || '').trim();

    function setPlaceholder(sel, teks, nonAktif) {
        sel.disabled = !!nonAktif;
        sel.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = teks;
        sel.appendChild(opt);
    }

    function isiOpsi(sel, daftar, savedValue, placeholder) {
        sel.disabled = false;
        sel.innerHTML = '';

        const opsiKosong = document.createElement('option');
        opsiKosong.value = '';
        opsiKosong.textContent = placeholder;
        sel.appendChild(opsiKosong);

        const targetLower = savedValue ? savedValue.toLowerCase() : '';
        let cocok = false;

        daftar.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.dataset.id = item.id;
            opt.textContent = item.name;
            if (targetLower && item.name.toLowerCase() === targetLower) {
                opt.selected = true;
                cocok = true;
            }
            sel.appendChild(opt);
        });

        return cocok;
    }

    async function ambilJSON(url) {
        try {
            const resp = await fetch(url);
            if (!resp.ok) {
                throw new Error('HTTP ' + resp.status);
            }
            return await resp.json();
        } catch (err) {
            console.error('Gagal memuat alamat:', err);
            return null;
        }
    }

    async function muatProvinsi() {
        setPlaceholder(selProvinsi, 'Memuat provinsi...', true);
        setPlaceholder(selKota, '-- Pilih provinsi dulu --', true);
        setPlaceholder(selKecamatan, '-- Pilih kota dulu --', true);

        const data = await ambilJSON(API_BASE + '/provinces.json');
        if (!data) {
            setPlaceholder(selProvinsi, 'Gagal memuat — refresh halaman', true);
            return;
        }

        const cocok = isiOpsi(selProvinsi, data, savedProvinsi, '-- Pilih provinsi --');
        if (cocok) {
            const id = selProvinsi.selectedOptions[0].dataset.id;
            await muatKota(id, savedKota, savedKecamatan);
        }
    }

    async function muatKota(provinsiId, savedKotaTarget, savedKecamatanTarget) {
        setPlaceholder(selKota, 'Memuat kota...', true);
        setPlaceholder(selKecamatan, '-- Pilih kota dulu --', true);

        const data = await ambilJSON(API_BASE + '/regencies/' + provinsiId + '.json');
        if (!data) {
            setPlaceholder(selKota, 'Gagal memuat', true);
            return;
        }

        const cocok = isiOpsi(selKota, data, savedKotaTarget || '', '-- Pilih kota/kabupaten --');
        if (cocok) {
            const id = selKota.selectedOptions[0].dataset.id;
            await muatKecamatan(id, savedKecamatanTarget || '');
        }
    }

    async function muatKecamatan(kotaId, savedKecamatanTarget) {
        setPlaceholder(selKecamatan, 'Memuat kecamatan...', true);

        const data = await ambilJSON(API_BASE + '/districts/' + kotaId + '.json');
        if (!data) {
            setPlaceholder(selKecamatan, 'Gagal memuat', true);
            return;
        }

        isiOpsi(selKecamatan, data, savedKecamatanTarget || '', '-- Pilih kecamatan --');
    }

    selProvinsi.addEventListener('change', function () {
        const opt = selProvinsi.selectedOptions[0];
        const id = opt ? opt.dataset.id : '';
        if (id) {
            muatKota(id, '', '');
        } else {
            setPlaceholder(selKota, '-- Pilih provinsi dulu --', true);
            setPlaceholder(selKecamatan, '-- Pilih kota dulu --', true);
        }
    });

    selKota.addEventListener('change', function () {
        const opt = selKota.selectedOptions[0];
        const id = opt ? opt.dataset.id : '';
        if (id) {
            muatKecamatan(id, '');
        } else {
            setPlaceholder(selKecamatan, '-- Pilih kota dulu --', true);
        }
    });

    muatProvinsi();
})();
