/**
 * Peta pemilih titik alamat (Leaflet + OpenStreetMap).
 * Fitur:
 *   - Tampilkan peta di #peta-alamat
 *   - Klik di peta atau drag marker untuk menentukan titik
 *   - Tombol "Lokasi saya" pakai browser Geolocation API
 *   - Reverse geocoding (Nominatim) untuk menampilkan nama tempat di info
 *   - Lat/lng disinkronkan ke input hidden [data-peta-lat] & [data-peta-lng]
 *
 * Dependensi: window.L (Leaflet) — dimuat via CDN sebelum script ini.
 */
(function () {
    'use strict';

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

        // Pusat default: tengah Pulau Jawa (jangkauan luas, sebagian besar pembeli)
        const PUSAT_DEFAULT = [-7.5, 110.5];
        const ZOOM_DEFAULT = 5;
        const ZOOM_TITIK = 16;

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

        function setKoordinat(lat, lng, alasan) {
            const latFix = Number(lat.toFixed(7));
            const lngFix = Number(lng.toFixed(7));
            inputLat.value = String(latFix);
            inputLng.value = String(lngFix);

            if (!marker) {
                marker = L.marker([latFix, lngFix], { draggable: true }).addTo(peta);
                marker.on('dragend', function () {
                    const p = marker.getLatLng();
                    setKoordinat(p.lat, p.lng, 'drag');
                });
            } else {
                marker.setLatLng([latFix, lngFix]);
            }

            if (info) {
                info.innerHTML = 'Titik terpilih: <strong data-peta-koordinat>' + latFix + ', ' + lngFix + '</strong>'
                    + ' <span class="peta-alamat__memuat">memuat nama tempat&hellip;</span>';
            }
            balikkanGeocode(latFix, lngFix);
        }

        function balikkanGeocode(lat, lng) {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat='
                + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!info) return;
                    const koord = '<strong data-peta-koordinat>' + lat + ', ' + lng + '</strong>';
                    if (data && data.display_name) {
                        const nama = data.display_name.replace(/, Indonesia$/, '');
                        info.innerHTML = 'Titik terpilih: ' + koord + '<br><span class="peta-alamat__nama">' + escapeHTML(nama) + '</span>';
                    } else {
                        info.innerHTML = 'Titik terpilih: ' + koord;
                    }
                })
                .catch(function () {
                    if (!info) return;
                    info.innerHTML = 'Titik terpilih: <strong data-peta-koordinat>' + lat + ', ' + lng + '</strong>';
                });
        }

        function escapeHTML(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        // Marker awal kalau sudah ada koordinat tersimpan
        if (adaTitikAwal) {
            setKoordinat(latAwal, lngAwal, 'awal');
        }

        // Klik di peta → set titik baru
        peta.on('click', function (e) {
            setKoordinat(e.latlng.lat, e.latlng.lng, 'klik');
        });

        // Tombol "Lokasi saya"
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
                        peta.setView([c.latitude, c.longitude], ZOOM_TITIK);
                        setKoordinat(c.latitude, c.longitude, 'gps');
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

        // Pastikan ukuran peta benar setelah render (kalau ada layout shift)
        setTimeout(function () { peta.invalidateSize(); }, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
