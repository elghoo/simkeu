// SIMKEU - skrip umum
(function () {
    // Toggle sidebar (mobile)
    document.querySelectorAll('[data-toggle-sidebar]').forEach(function (el) {
        el.addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('show');
            document.querySelector('.sidebar-backdrop').classList.toggle('show');
        });
    });

    // Format input rupiah otomatis: 1500000 -> 1.500.000
    window.formatRibuan = function (v) {
        v = String(v).replace(/[^0-9]/g, '');
        return v ? parseInt(v, 10).toLocaleString('id-ID') : '';
    };
    window.parseRibuan = function (v) {
        return parseInt(String(v || '').replace(/[^0-9]/g, ''), 10) || 0;
    };
    window.rupiah = function (n) {
        return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
    };
    document.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('input-rupiah')) {
            var pos = e.target.value.length - e.target.selectionStart;
            e.target.value = formatRibuan(e.target.value);
            var p = Math.max(0, e.target.value.length - pos);
            e.target.setSelectionRange(p, p);
        }
    });
    document.querySelectorAll('.input-rupiah').forEach(function (el) {
        el.value = formatRibuan(el.value);
    });

    // Konfirmasi hapus
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !confirm(msg)) e.preventDefault();
    });
})();

// Terbilang (bahasa Indonesia) untuk bantuan input nominal
window.terbilang = function (n) {
    n = Math.floor(Math.abs(n));
    var h = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    function t(x) {
        if (x < 12) return h[x];
        if (x < 20) return t(x - 10) + ' belas';
        if (x < 100) return (t(Math.floor(x / 10)) + ' puluh ' + t(x % 10)).trim();
        if (x < 200) return ('seratus ' + t(x - 100)).trim();
        if (x < 1000) return (t(Math.floor(x / 100)) + ' ratus ' + t(x % 100)).trim();
        if (x < 2000) return ('seribu ' + t(x - 1000)).trim();
        if (x < 1e6) return (t(Math.floor(x / 1000)) + ' ribu ' + t(x % 1000)).trim();
        if (x < 1e9) return (t(Math.floor(x / 1e6)) + ' juta ' + t(x % 1e6)).trim();
        if (x < 1e12) return (t(Math.floor(x / 1e9)) + ' miliar ' + t(x % 1e9)).trim();
        return (t(Math.floor(x / 1e12)) + ' triliun ' + t(x % 1e12)).trim();
    }
    var s = t(n);
    return s.charAt(0).toUpperCase() + s.slice(1);
};
