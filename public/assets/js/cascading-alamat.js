/**
 * Cascading dropdown alamat Indonesia (provinsi → kota → kecamatan).
 * Sumber data: https://www.emsifa.com/api-wilayah-indonesia (BPS, tanpa API key).
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

    let resolveWilayahSiap = null;
    window.aplikasiWilayahSiap = new Promise(function (resolve) {
        resolveWilayahSiap = resolve;
    });

    let antrianPeta = Promise.resolve();

    function tandaiWilayahSiap() {
        if (resolveWilayahSiap) {
            resolveWilayahSiap();
            resolveWilayahSiap = null;
        }
    }

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

        daftar.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.dataset.id = item.id;
            opt.textContent = item.name;
            sel.appendChild(opt);
        });

        if (savedValue) {
            const opt = cariOpsiTerbaik(sel, [savedValue]);
            if (opt) {
                opt.selected = true;
                return true;
            }
        }

        return false;
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
            tandaiWilayahSiap();
            return;
        }

        const cocok = isiOpsi(selProvinsi, data, savedProvinsi, '-- Pilih provinsi --');
        if (cocok) {
            const id = selProvinsi.selectedOptions[0].dataset.id;
            await muatKota(id, savedKota, savedKecamatan);
        }
        tandaiWilayahSiap();
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

    /** Normalisasi nama wilayah agar cocok Nominatim ↔ data emsifa (BPS). */
    function normalisasiWilayah(nama) {
        return String(nama || '')
            .toLowerCase()
            .replace(/\./g, '')
            .replace(/^(provinsi|prov)\s+/i, '')
            .replace(/^(kabupaten|kab|kota administrasi|kota|kecamatan|kec|kelurahan|kel|desa)\s+/i, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function daftarUnik(arr) {
        const seen = {};
        const out = [];
        arr.forEach(function (item) {
            const s = String(item || '').trim();
            if (!s) return;
            const key = normalisasiWilayah(s);
            if (seen[key]) return;
            seen[key] = true;
            out.push(s);
        });
        return out;
    }

    function skorCocok(namaOpsi, target) {
        const a = normalisasiWilayah(namaOpsi);
        const b = normalisasiWilayah(target);
        if (!a || !b) return 0;
        if (a === b) return 100;
        if (a.indexOf(b) !== -1 || b.indexOf(a) !== -1) return 85;

        const tokenA = a.split(' ').filter(Boolean);
        const tokenB = b.split(' ').filter(Boolean);
        let hit = 0;
        tokenB.forEach(function (tb) {
            if (tokenA.some(function (ta) { return ta === tb || ta.indexOf(tb) !== -1 || tb.indexOf(ta) !== -1; })) {
                hit += 1;
            }
        });
        if (hit === 0) return 0;
        return Math.round((hit / Math.max(tokenA.length, tokenB.length)) * 75);
    }

    /**
     * Pilih opsi terbaik dari beberapa kandidat nama (urutan prioritas tetap).
     */
    function cariOpsiTerbaik(sel, kandidat, ambang) {
        const batas = typeof ambang === 'number' ? ambang : 55;
        const list = daftarUnik(kandidat);
        let terbaik = null;
        let skorTerbaik = 0;

        list.forEach(function (target) {
            for (let i = 0; i < sel.options.length; i++) {
                const opt = sel.options[i];
                if (!opt.value) continue;
                const skor = skorCocok(opt.value, target);
                if (skor > skorTerbaik) {
                    skorTerbaik = skor;
                    terbaik = opt;
                }
            }
        });

        return skorTerbaik >= batas ? terbaik : null;
    }

    function cariOpsi(sel, target) {
        return cariOpsiTerbaik(sel, [target]);
    }

    /** Kandidat nama kota/kab — jangan masukkan city_district (itu level kecamatan). */
    function kandidatKotaDariAddr(addr) {
        return daftarUnik([
            addr.city,
            addr.county,
            addr.town,
            addr.municipality,
        ]);
    }

    /**
     * Kandidat kecamatan: prioritas field level kecamatan dari Nominatim + cuplikan display_name.
     */
    function kandidatKecamatanDariAddr(addr, displayName) {
        const prioritas = daftarUnik([
            addr.city_district,
            addr.municipality,
            addr.state_district,
            addr.subdistrict,
            addr.district,
        ]);
        const kelurahan = daftarUnik([
            addr.suburb,
            addr.neighbourhood,
            addr.quarter,
            addr.hamlet,
            addr.village,
            addr.residential,
        ]);

        if (displayName) {
            const abaikan = /^(indonesia|sumatra|sumatera|jawa|bali|kalimantan|sulawesi|papua|maluku|riau|aceh|lampung|bengkulu|jambi|ntb|ntt|banten|yogyakarta|jakarta)$/i;
            displayName.split(',').forEach(function (potong) {
                const p = potong.trim();
                if (!p || abaikan.test(normalisasiWilayah(p))) return;
                if (/^\d{5}$/.test(p)) return;
                if (prioritas.indexOf(p) === -1 && kelurahan.indexOf(p) === -1) {
                    prioritas.push(p);
                }
            });
        }

        return prioritas.concat(kelurahan);
    }

    /** Pilih kecamatan — ambang lebih rendah + cocok token agar nama emsifa/BPS terpilih. */
    function cariOpsiKecamatan(sel, kandidat) {
        const opt = cariOpsiTerbaik(sel, kandidat, 50);
        if (opt) return opt;

        const list = daftarUnik(kandidat);
        let terbaik = null;
        let skorTerbaik = 0;

        list.forEach(function (target) {
            const tokenTarget = normalisasiWilayah(target).split(' ').filter(function (t) {
                return t.length > 2;
            });
            if (tokenTarget.length === 0) return;

            for (let i = 0; i < sel.options.length; i++) {
                const optItem = sel.options[i];
                if (!optItem.value) continue;
                const tokenOpsi = normalisasiWilayah(optItem.value).split(' ').filter(function (t) {
                    return t.length > 2;
                });
                let hit = 0;
                tokenTarget.forEach(function (tb) {
                    if (tokenOpsi.some(function (ta) {
                        return ta === tb || ta.indexOf(tb) !== -1 || tb.indexOf(ta) !== -1;
                    })) {
                        hit += 1;
                    }
                });
                const skor = Math.round((hit / tokenTarget.length) * 90);
                if (skor > skorTerbaik) {
                    skorTerbaik = skor;
                    terbaik = optItem;
                }
            }
        });

        return skorTerbaik >= 40 ? terbaik : null;
    }

    function bangunAlamatDetail(addr) {
        const jalan = [];
        if (addr.road) {
            jalan.push(addr.road);
        } else if (addr.pedestrian) {
            jalan.push(addr.pedestrian);
        } else if (addr.footway) {
            jalan.push(addr.footway);
        }
        if (addr.house_number) {
            if (jalan.length) {
                jalan[0] = jalan[0] + ' No. ' + addr.house_number;
            } else {
                jalan.push('No. ' + addr.house_number);
            }
        }
        const lingkungan = [
            addr.neighbourhood,
            addr.quarter,
            addr.hamlet,
            addr.residential,
            addr.suburb,
        ].filter(Boolean);
        lingkungan.forEach(function (x) {
            if (jalan.indexOf(x) === -1) {
                jalan.push(x);
            }
        });
        return jalan.join(', ').trim();
    }

    async function isiDariPeta(addr, displayName) {
        await window.aplikasiWilayahSiap;

        if (!addr || typeof addr !== 'object') {
            return { ok: false, pesan: 'Data alamat peta tidak valid.' };
        }

        const kandidatProvinsi = daftarUnik([addr.state, addr.region]);
        const optProvinsi = cariOpsiTerbaik(selProvinsi, kandidatProvinsi);
        if (!optProvinsi || !optProvinsi.dataset.id) {
            return { ok: false, pesan: 'Provinsi tidak ditemukan di daftar. Pilih manual di dropdown.' };
        }

        selProvinsi.value = optProvinsi.value;
        const provinsiId = optProvinsi.dataset.id;

        const kandidatKota = kandidatKotaDariAddr(addr);
        const kandidatKec = kandidatKecamatanDariAddr(addr, displayName || '');

        const targetKota = kandidatKota[0] || '';
        await muatKota(provinsiId, targetKota, '');

        const optKota = cariOpsiTerbaik(selKota, kandidatKota);
        if (optKota && optKota.dataset.id) {
            selKota.value = optKota.value;
            await muatKecamatan(optKota.dataset.id, '');
            const optKec = cariOpsiKecamatan(selKecamatan, kandidatKec);
            if (optKec) {
                selKecamatan.value = optKec.value;
            }
        }

        const inputKodePos = document.querySelector('input[name="kode_pos"]');
        if (inputKodePos && addr.postcode) {
            inputKodePos.value = String(addr.postcode).replace(/\D/g, '').slice(0, 6);
        }

        const textareaDetail = document.querySelector('textarea[name="alamat_detail"]');
        const detailBaru = bangunAlamatDetail(addr);
        if (textareaDetail && detailBaru) {
            textareaDetail.value = detailBaru;
        }

        document.dispatchEvent(new CustomEvent('alamat-peta-diisi', {
            bubbles: true,
            detail: {
                provinsi: selProvinsi.value,
                kota: selKota.value,
                kecamatan: selKecamatan.value,
                kode_pos: inputKodePos ? inputKodePos.value : '',
                alamat_detail: textareaDetail ? textareaDetail.value : '',
            },
        }));

        return {
            ok: true,
            provinsi: !!selProvinsi.value,
            kota: !!selKota.value,
            kecamatan: !!selKecamatan.value,
        };
    }

    /**
     * Isi cascading dropdown + kode pos + alamat detail dari reverse-geocode peta.
     */
    window.aplikasiAlamatDariPeta = function (addr, displayName) {
        antrianPeta = antrianPeta
            .then(function () { return isiDariPeta(addr, displayName); })
            .catch(function (err) {
                console.error('Gagal isi alamat dari peta:', err);
                return { ok: false, pesan: 'Gagal mengisi formulir dari peta.' };
            });
        return antrianPeta;
    };

    muatProvinsi();
})();