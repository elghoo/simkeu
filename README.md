# SIMKEU — Sistem Informasi Manajemen Keuangan

Aplikasi manajemen keuangan lembaga pendidikan berbasis **PHP Native** (tanpa framework) dan **MySQL/MariaDB**. Antarmuka memakai Bootstrap 5 dan Chart.js via CDN.

## Fitur

- **Dashboard**: ringkasan saldo seluruh akun kas, pemasukan dan pengeluaran bulan berjalan, grafik arus kas 12 bulan, komposisi pengeluaran, realisasi anggaran, transaksi terbaru, dan pengajuan dana aktif.
- **Transaksi pemasukan dan pengeluaran**: nomor bukti otomatis (BKM/BKK), kategori, unit kerja, akun kas, pihak terkait, unggah bukti (JPG/PNG/PDF), serta cek saldo agar akun tidak minus.
- **Transfer kas** antar akun, misalnya dari rekening ke kas tunai.
- **Pengajuan dana unit**. Alurnya: unit mengajukan, pimpinan menyetujui atau menolak (bisa dengan nominal disetujui berbeda dan catatan), lalu bendahara mencairkan. Pencairan otomatis tercatat sebagai pengeluaran.
- **Anggaran (RAPB)** per tahun dan per pos, dengan fitur salin dari tahun sebelumnya serta pemantauan realisasi dan sisa anggaran.
- **Laporan**:
  - penerimaan dan pengeluaran
  - buku kas per akun (saldo berjalan)
  - jurnal transaksi
  - realisasi anggaran
  - rekap per unit kerja
  
  Semua laporan bisa dicetak dengan kop dan tanda tangan, serta diekspor ke Excel (CSV).
- **Data master**: akun kas/bank, kategori, dan unit kerja.
- **Sistem**: manajemen pengguna dengan 4 peran, pengaturan lembaga, log aktivitas, dan profil/ganti password.

## Kebutuhan Sistem

- PHP **8.0 atau lebih baru**, dengan ekstensi `pdo_mysql`, `mbstring`, dan `fileinfo`
- MySQL 5.7+ / MariaDB 10.3+
- Apache (XAMPP/Laragon) atau web server lain

## Instalasi (XAMPP / Laragon)

1. Salin folder `simkeu` ke `htdocs` (XAMPP) atau `www` (Laragon), sehingga menjadi `htdocs/simkeu`.
2. Buka phpMyAdmin, pilih menu **Import**, lalu pilih file `database/simkeu_db.sql`. File ini akan membuat database `simkeu_db` beserta data contoh.
3. Sesuaikan koneksi di `config/db.php` (`DB_HOST`, `DB_USER`, `DB_PASS`). Bawaannya adalah `root` tanpa password.
4. Buka http://localhost/simkeu.

## Akun Demo

| Peran | Username | Password |
|---|---|---|
| Administrator | admin | admin123 |
| Bendahara | bendahara | bendahara123 |
| Pimpinan | pimpinan | pimpinan123 |
| Staf Unit (Prodi PAI) | staf | staf123 |

Segera ganti semua password setelah instalasi.

## Hak Akses

| Fitur | Admin | Bendahara | Pimpinan | Staf |
|---|:-:|:-:|:-:|:-:|
| Dashboard, lihat transaksi, laporan | Ya | Ya | Ya | - |
| Input/edit transaksi, transfer | Ya | Ya | - | - |
| Hapus transaksi | Ya | - | - | - |
| Data master & anggaran | Ya | Ya | - | - |
| Lihat pengajuan dana | Ya | Ya | Ya | Unit sendiri |
| Buat pengajuan | Ya | Ya | - | Ya |
| Setujui/tolak pengajuan | Ya | - | Ya | - |
| Cairkan dana | Ya | Ya | - | - |
| Pengguna, pengaturan, log | Ya | - | - | - |

## Struktur Folder

```
simkeu/
├── index.php            # router utama (?page=...)
├── auth/                # login & logout
├── config/              # app.php, db.php
├── includes/            # functions.php, header.php, footer.php
├── modules/             # halaman per modul (dashboard, transaksi, pengajuan, laporan, ...)
├── assets/css, js/      # style.css, app.js
├── database/            # simkeu_db.sql
└── uploads/bukti/       # berkas bukti transaksi
```

## Catatan Keamanan

- Semua query memakai **PDO prepared statements**.
- Password disimpan dengan `password_hash()` (bcrypt).
- Setiap form dilindungi **token CSRF**.
- Unggahan divalidasi berdasarkan ekstensi dan MIME, dengan batas 2 MB, dan disimpan dengan nama acak.
- File `.htaccess` memblokir akses langsung ke `config/`, `includes/`, dan `database/`, serta mencegah eksekusi PHP di `uploads/`. File ini hanya berlaku di Apache. Untuk Nginx, buat aturan serupa.
- Untuk server produksi, ganti password demo, buat user database khusus, dan matikan `display_errors`.
