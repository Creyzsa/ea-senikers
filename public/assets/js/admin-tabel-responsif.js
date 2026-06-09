(function () {
    'use strict';

    function labelSatuTabel(table) {
        if (!table || !table.tHead || !table.tBodies[0]) {
            return;
        }

        var headers = [];
        table.tHead.querySelectorAll('th').forEach(function (th, index) {
            headers[index] = (th.textContent || '').trim();
        });

        table.tBodies[0].querySelectorAll('tr').forEach(function (row) {
            if (row.classList.contains('admin-tr-kosong')) {
                return;
            }

            row.querySelectorAll('td').forEach(function (cell, index) {
                if (cell.colSpan > 1) {
                    cell.removeAttribute('data-label');
                    return;
                }

                var label = headers[index];
                if (label) {
                    cell.setAttribute('data-label', label);
                }
            });
        });
    }

    window.adminSiapkanTabel = function (root) {
        if (!document.body.classList.contains('halaman-admin')) {
            return;
        }

        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.admin-tabel').forEach(labelSatuTabel);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.adminSiapkanTabel(document);
        });
    } else {
        window.adminSiapkanTabel(document);
    }
})();