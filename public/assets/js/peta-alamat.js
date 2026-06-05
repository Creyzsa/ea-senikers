/**
 * Peta pemilih titik alamat (Leaflet + OpenStreetMap).
 */
(function () {
    'use strict';

    const ZOOM_DEFAULT = 5;
    const ZOOM_TITIK = 16;
    const PUSAT_DEFAULT = [-7.5, 110.5];
    const DEBOUNCE_MS = 450;

    function init() {
        if (typeof window.L === 'undefined') {
            console.error('Leaflet belum dimuat.');
            return;
        }

        const wrap = document.querySelector('[data-peta-wrap]');
        const kanvas = document.getElementById('peta-alamat');
        const inputLat = document.querySelector('[data-peta-lat]');
        const inputLng = document.querySelector('[data-peta-lng]');
        const tombolLokasi = document.querySelector('[data-peta-lokasi-saya]');
        const info = document.querySelector('[data-peta-info]');

        if (!wrap || !kanvas || !inputLat || !inputLng) {
            return;
        }

        const latAwal = parseFloat(inputLat.value);
        const lngAwal = parseFloat(inputLng.value);
        const adaTitikAwal = !isNaN(latAwal) && !isNaN(lngAwal);

        const peta = L.map(kanvas, {
            center: adaTitikAwal ? [latAwal, lngAwal] : PUSAT_DEFAULT,
            zoom: adaTitikAwal ? ZOOM_TITIK : ZOOM_DEFAULT,
        });

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(peta);

        let marker = null;
        let debounceTimer = null;
        let urutanGeocode = 0;

        function escapeHTML(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        function tampilkanInfoKoord(lat, lng, statusHTML) {
            if (!info) return;
            const koord = '<strong data-peta-koordinat>' + lat + ', ' + lng + '</strong>';
            info.innerHTML = 'Titik terpilih: ' + koord + (statusHTML ? ' ' + statusHTML : '');
        }

        function balikkanGeocode(lat, lng) {
            const urutan = ++urutanGeocode;
            tampilkanInfoKoord(lat, lng, '<span class="peta-alamat__memuat">memuat alamat&hellip;</span>');

            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=id&lat='
                + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);

            fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'Accept-Language': 'id',
                },
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (urutan !== urutanGeocode) return;

                    const koord = '<strong data-peta-koordinat>' + lat + ', ' + lng + '</strong>';
                    if (!info) {
                        sinkronkanForm(data);
                        return;
                    }

                    if (data && data.display_name) {
                        const nama = data.display_name.replace(/, Indonesia$/i, '');
                        info.innerHTML = 'Titik terpilih: ' + koord
                            + '<br><span class="peta-alamat__nama">' + escapeHTML(nama) + '</span>'
                            + ' <span class="peta-alamat__memuat">menyinkronkan formulir&hellip;</span>';
                    } else {
                        info.innerHTML = 'Titik terpilih: ' + koord;
                    }

                    sinkronkanForm(data);
                })
                .catch(function () {
                    if (urutan !== urutanGeocode) return;
                    tampilkanInfoKoord(lat, lng, '<span class="peta-alamat__error">gagal memuat nama tempat</span>');
                });
        }

        function sinkronkanForm(data) {
            if (!data || !data.address || typeof window.aplikasiAlamatDariPeta !== 'function') {
                return;
            }

            const hasil = window.aplikasiAlamatDariPeta(data.address, data.display_name || '');
            if (!hasil || typeof hasil.then !== 'function') {
                return;
            }

            hasil.then(function (ringkasan) {
                if (!info) return;
                const koordEl = info.querySelector('[data-peta-koordinat]');
                const lat = koordEl ? koordEl.textContent.split(',')[0].trim() : inputLat.value;
                const lng = koordEl ? koordEl.textContent.split(',')[1].trim() : inputLng.value;
                const koord = '<strong data-peta-koordinat>' + lat + ', ' + lng + '</strong>';
                const namaEl = info.querySelector('.peta-alamat__nama');
                const nama = namaEl ? namaEl.outerHTML : '';

                if (ringkasan && ringkasan.ok === false) {
                    info.innerHTML = 'Titik terpilih: ' + koord + (nama ? '<br>' + nama : '')
                        + '<br><span class="peta-alamat__error">' + escapeHTML(ringkasan.pesan || 'Sebagian alamat perlu dipilih manual.') + '</span>';
                    return;
                }

                const bagian = [];
                if (ringkasan && ringkasan.provinsi) bagian.push('provinsi');
                if (ringkasan && ringkasan.kota) bagian.push('kota');
                if (ringkasan && ringkasan.kecamatan) bagian.push('kecamatan');
                const status = bagian.length
                    ? '<br><small>Form terisi: ' + bagian.join(', ') + ', kode pos & alamat detail.</small>'
                    : '<br><small>Koordinat tersimpan. Lengkapi dropdown bila perlu.</small>';

                info.innerHTML = 'Titik terpilih: ' + koord + (nama ? '<br>' + nama : '') + status;
            });
        }

        function setKoordinat(lat, lng, sinkronkanForm) {
            const latFix = Number(lat.toFixed(7));
            const lngFix = Number(lng.toFixed(7));
            inputLat.value = String(latFix);
            inputLng.value = String(lngFix);

            if (sinkronkanForm !== false) {
                peta.setView([latFix, lngFix], ZOOM_TITIK, { animate: true });
            }

            if (!marker) {
                marker = L.marker([latFix, lngFix], { draggable: true }).addTo(peta);
                marker.on('dragend', function () {
                    const p = marker.getLatLng();
                    setKoordinat(p.lat, p.lng, true);
                });
            } else {
                marker.setLatLng([latFix, lngFix]);
            }

            if (sinkronkanForm !== false) {
                jadwalkanGeocode(latFix, lngFix);
            } else {
                tampilkanInfoKoord(latFix, lngFix, '');
            }
        }

        function jadwalkanGeocode(lat, lng) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                balikkanGeocode(lat, lng);
            }, DEBOUNCE_MS);
        }

        if (adaTitikAwal) {
            setKoordinat(latAwal, lngAwal, false);
        }

        peta.on('click', function (e) {
            setKoordinat(e.latlng.lat, e.latlng.lng, true);
        });

        if (tombolLokasi && navigator.geolocation) {
            tombolLokasi.addEventListener('click', function () {
                if (info) {
                    info.innerHTML = '<span class="peta-alamat__memuat">Mencari lokasi Anda&hellip;</span>';
                }
                tombolLokasi.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        tombolLokasi.disabled = false;
                        const c = pos.coords;
                        setKoordinat(c.latitude, c.longitude, true);
                    },
                    function (err) {
                        tombolLokasi.disabled = false;
                        if (info) {
                            const pesan = err.code === 1
                                ? 'Izin lokasi ditolak. Aktifkan di pengaturan browser.'
                                : 'Tidak bisa mengambil lokasi GPS. Klik peta untuk pilih manual.';
                            info.innerHTML = '<span class="peta-alamat__error">' + pesan + '</span>';
                        }
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                );
            });
        } else if (tombolLokasi) {
            tombolLokasi.disabled = true;
            tombolLokasi.title = 'Browser tidak mendukung Geolocation';
        }

        setTimeout(function () { peta.invalidateSize(); }, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();