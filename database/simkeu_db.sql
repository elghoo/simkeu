-- =========================================================
-- SIMKEU - Sistem Informasi Manajemen Keuangan
-- Database MySQL / MariaDB
-- Import file ini lewat phpMyAdmin atau: mysql -u root -p < simkeu_db.sql
-- =========================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS simkeu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simkeu_db;

DROP TABLE IF EXISTS log_aktivitas;
DROP TABLE IF EXISTS pengajuan;
DROP TABLE IF EXISTS anggaran;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS akun_kas;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS unit;
DROP TABLE IF EXISTS pengaturan;

-- ---------------------------------------------------------
-- Pengaturan lembaga (key-value)
-- ---------------------------------------------------------
CREATE TABLE pengaturan (
    kunci VARCHAR(50) PRIMARY KEY,
    nilai TEXT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Unit kerja / bagian
-- ---------------------------------------------------------
CREATE TABLE unit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    penanggung_jawab VARCHAR(100) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Pengguna
--   admin     : akses penuh
--   bendahara : kelola transaksi, data master, anggaran, pencairan
--   pimpinan  : melihat laporan & menyetujui pengajuan dana
--   staf      : membuat pengajuan dana untuk unitnya
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','bendahara','pimpinan','staf') NOT NULL DEFAULT 'staf',
    unit_id INT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_unit FOREIGN KEY (unit_id) REFERENCES unit(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Akun kas & rekening bank
-- ---------------------------------------------------------
CREATE TABLE akun_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    jenis ENUM('kas','bank') NOT NULL DEFAULT 'kas',
    nama_bank VARCHAR(100) NULL,
    no_rekening VARCHAR(50) NULL,
    atas_nama VARCHAR(100) NULL,
    saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0,
    aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Kategori (pos) pemasukan & pengeluaran
-- ---------------------------------------------------------
CREATE TABLE kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    tipe ENUM('masuk','keluar') NOT NULL,
    keterangan VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Transaksi kas: masuk, keluar, transfer antar akun
-- ---------------------------------------------------------
CREATE TABLE transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_transaksi VARCHAR(30) NOT NULL UNIQUE,
    tanggal DATE NOT NULL,
    tipe ENUM('masuk','keluar','transfer') NOT NULL,
    akun_id INT NOT NULL,
    akun_tujuan_id INT NULL,
    kategori_id INT NULL,
    unit_id INT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    pihak VARCHAR(150) NULL COMMENT 'Diterima dari / dibayarkan kepada',
    keterangan VARCHAR(255) NOT NULL,
    no_referensi VARCHAR(50) NULL,
    bukti VARCHAR(255) NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tanggal (tanggal),
    INDEX idx_tipe (tipe),
    CONSTRAINT fk_trx_akun FOREIGN KEY (akun_id) REFERENCES akun_kas(id),
    CONSTRAINT fk_trx_akun_tujuan FOREIGN KEY (akun_tujuan_id) REFERENCES akun_kas(id),
    CONSTRAINT fk_trx_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id),
    CONSTRAINT fk_trx_unit FOREIGN KEY (unit_id) REFERENCES unit(id) ON DELETE SET NULL,
    CONSTRAINT fk_trx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Anggaran (RAPB) per kategori per tahun
-- ---------------------------------------------------------
CREATE TABLE anggaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun YEAR NOT NULL,
    kategori_id INT NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
    keterangan VARCHAR(255) NULL,
    UNIQUE KEY uk_tahun_kategori (tahun, kategori_id),
    CONSTRAINT fk_ang_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Pengajuan dana dari unit
-- ---------------------------------------------------------
CREATE TABLE pengajuan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_pengajuan VARCHAR(30) NOT NULL UNIQUE,
    tanggal DATE NOT NULL,
    unit_id INT NOT NULL,
    kategori_id INT NOT NULL,
    judul VARCHAR(150) NOT NULL,
    rincian TEXT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    jumlah_disetujui DECIMAL(15,2) NULL,
    status ENUM('diajukan','disetujui','ditolak','dicairkan') NOT NULL DEFAULT 'diajukan',
    catatan VARCHAR(255) NULL,
    user_id INT NULL,
    diputuskan_oleh INT NULL,
    tgl_keputusan DATETIME NULL,
    transaksi_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    CONSTRAINT fk_pgj_unit FOREIGN KEY (unit_id) REFERENCES unit(id),
    CONSTRAINT fk_pgj_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id),
    CONSTRAINT fk_pgj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pgj_pemutus FOREIGN KEY (diputuskan_oleh) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pgj_trx FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Log aktivitas pengguna
-- ---------------------------------------------------------
CREATE TABLE log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    aksi VARCHAR(50) NOT NULL,
    detail VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
-- ---------------------------------------------------------
-- ---------------------------------------------------------
-- DATA AWAL & DATA CONTOH
-- ---------------------------------------------------------

INSERT INTO pengaturan (kunci, nilai) VALUES
('nama_lembaga', 'Institut Muslim Cendekia'),
('singkatan', 'IMC'),
('alamat', 'Kabupaten Sukabumi, Jawa Barat'),
('telepon', ''),
('email', 'keuangan@imc.ac.id'),
('kota', 'Sukabumi'),
('nama_pimpinan', 'Dr. Ahmad Fauzi, M.Pd.'),
('jabatan_pimpinan', 'Rektor'),
('nama_bendahara', 'Siti Aminah, S.E.'),
('jabatan_bendahara', 'Bendahara'),
('tahun_anggaran', '2026');

INSERT INTO unit (id, kode, nama, penanggung_jawab) VALUES
(1, 'REK', 'Rektorat', 'Dr. Ahmad Fauzi, M.Pd.'),
(2, 'KEU', 'Bagian Keuangan', 'Siti Aminah, S.E.'),
(3, 'AKD', 'Bagian Akademik', 'Rahmat Hidayat, M.Pd.'),
(4, 'PAI', 'Prodi Pendidikan Agama Islam', 'Nurul Huda, M.Pd.I.'),
(5, 'MPI', 'Prodi Manajemen Pendidikan Islam', 'Dede Kurnia, M.Pd.'),
(6, 'KMH', 'Kemahasiswaan', 'Yusuf Maulana, S.Pd.I.'),
(7, 'SPR', 'Sarana & Prasarana', 'Asep Saepudin');

INSERT INTO users (id, nama, username, password, role, unit_id) VALUES
(1, 'Administrator', 'admin', '$2y$12$n6KezxJDRsYh9U0f/Q8ohe6DeHl.t3ddI6UoY9bGEGvv/Gz8O5mNC', 'admin', NULL),
(2, 'Siti Aminah, S.E.', 'bendahara', '$2y$12$9NbLBLXrIOpxkPNCP8XcqeeaPtfW3SXgBSJklW/WEfGdfBimbjKRi', 'bendahara', 2),
(3, 'Dr. Ahmad Fauzi, M.Pd.', 'pimpinan', '$2y$12$FNt.5buppysJICLfyGVDnuHVXlKGT38Xp2SUJIbleV0TRtLTmMuSG', 'pimpinan', 1),
(4, 'Nurul Huda, M.Pd.I.', 'staf', '$2y$12$nfckr413G8ENzuZ3evNW1uyyFkKxt6MtIQwrHH0PDEXF95MAfRsce', 'staf', 4);

INSERT INTO akun_kas (id, kode, nama, jenis, nama_bank, no_rekening, atas_nama, saldo_awal) VALUES
(1, 'KAS-01', 'Kas Tunai (Kas Kecil)', 'kas', NULL, NULL, NULL, 15000000),
(2, 'BNK-01', 'Rekening Operasional', 'bank', 'Bank Syariah Indonesia', '7123456789', 'Institut Muslim Cendekia', 250000000),
(3, 'BNK-02', 'Rekening Dana Hibah', 'bank', 'Bank Muamalat', '3310098765', 'Institut Muslim Cendekia', 50000000);

INSERT INTO kategori (id, kode, nama, tipe) VALUES
(1, 'PM-01', 'SPP / UKT Mahasiswa', 'masuk'),
(2, 'PM-02', 'Biaya Pendaftaran Mahasiswa Baru', 'masuk'),
(3, 'PM-03', 'Infaq & Donasi', 'masuk'),
(4, 'PM-04', 'Hibah & Bantuan Pemerintah', 'masuk'),
(5, 'PM-05', 'Biaya Wisuda', 'masuk'),
(6, 'PM-06', 'Pendapatan Lain-lain', 'masuk'),
(7, 'PK-01', 'Gaji & Honorarium', 'keluar'),
(8, 'PK-02', 'Listrik, Air & Internet', 'keluar'),
(9, 'PK-03', 'Alat Tulis Kantor', 'keluar'),
(10, 'PK-04', 'Kegiatan Akademik', 'keluar'),
(11, 'PK-05', 'Kegiatan Kemahasiswaan', 'keluar'),
(12, 'PK-06', 'Pemeliharaan Sarana Prasarana', 'keluar'),
(13, 'PK-07', 'Konsumsi & Rapat', 'keluar'),
(14, 'PK-08', 'Transportasi & Perjalanan Dinas', 'keluar'),
(15, 'PK-09', 'Penelitian & Pengabdian', 'keluar'),
(16, 'PK-10', 'Biaya Administrasi Bank', 'keluar');

INSERT INTO anggaran (tahun, kategori_id, jumlah) VALUES
(2026, 1, 1850000000),
(2026, 2, 180000000),
(2026, 3, 60000000),
(2026, 4, 150000000),
(2026, 5, 75000000),
(2026, 6, 20000000),
(2026, 7, 1150000000),
(2026, 8, 60000000),
(2026, 9, 24000000),
(2026, 10, 180000000),
(2026, 11, 90000000),
(2026, 12, 150000000),
(2026, 13, 45000000),
(2026, 14, 40000000),
(2026, 15, 80000000),
(2026, 16, 1200000);

INSERT INTO transaksi (id, no_transaksi, tanggal, tipe, akun_id, akun_tujuan_id, kategori_id, unit_id, jumlah, pihak, keterangan, no_referensi, user_id, created_at) VALUES
(1, 'TRF-20260102-0001', '2026-01-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Januari', NULL, 2, '2026-01-02 09:00:00'),
(2, 'BKM-20260103-0001', '2026-01-03', 'masuk', 2, NULL, 1, 2, 38200000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Januari', 'VA-0101', 2, '2026-01-03 09:01:00'),
(3, 'BKK-20260105-0001', '2026-01-05', 'keluar', 2, NULL, 8, 7, 4000000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Januari', NULL, 2, '2026-01-05 09:02:00'),
(4, 'BKM-20260106-0001', '2026-01-06', 'masuk', 2, NULL, 1, 2, 40050000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Januari', 'VA-0102', 2, '2026-01-06 09:03:00'),
(5, 'BKM-20260107-0001', '2026-01-07', 'masuk', 1, NULL, 3, 1, 3250000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Januari', NULL, 2, '2026-01-07 09:04:00'),
(6, 'BKK-20260109-0001', '2026-01-09', 'keluar', 1, NULL, 9, 2, 1400000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-01-09 09:05:00'),
(7, 'BKM-20260109-0001', '2026-01-09', 'masuk', 2, NULL, 1, 2, 30850000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Januari', 'VA-0103', 2, '2026-01-09 09:06:00'),
(8, 'BKK-20260112-0001', '2026-01-12', 'keluar', 1, NULL, 13, 1, 1600000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Januari', NULL, 2, '2026-01-12 09:07:00'),
(9, 'BKM-20260112-0001', '2026-01-12', 'masuk', 2, NULL, 1, 2, 31550000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Januari', 'VA-0104', 2, '2026-01-12 09:08:00'),
(10, 'BKK-20260114-0001', '2026-01-14', 'keluar', 1, NULL, 14, 1, 500000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-01-14 09:09:00'),
(11, 'BKK-20260118-0001', '2026-01-18', 'keluar', 2, NULL, 10, 3, 22500000, 'Panitia Ujian', 'Honor pengawas & pencetakan soal UAS', NULL, 2, '2026-01-18 09:10:00'),
(12, 'BKK-20260125-0001', '2026-01-25', 'keluar', 2, NULL, 7, 2, 92500000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Januari', NULL, 2, '2026-01-25 09:11:00'),
(13, 'BKK-20260128-0001', '2026-01-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Januari', NULL, 2, '2026-01-28 09:12:00'),
(14, 'TRF-20260202-0001', '2026-02-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Februari', NULL, 2, '2026-02-02 09:13:00'),
(15, 'BKM-20260203-0001', '2026-02-03', 'masuk', 2, NULL, 1, 2, 30650000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Februari', 'VA-0201', 2, '2026-02-03 09:14:00'),
(16, 'BKK-20260205-0001', '2026-02-05', 'keluar', 2, NULL, 8, 7, 4900000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Februari', NULL, 2, '2026-02-05 09:15:00'),
(17, 'BKM-20260206-0001', '2026-02-06', 'masuk', 2, NULL, 1, 2, 41100000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Februari', 'VA-0202', 2, '2026-02-06 09:16:00'),
(18, 'BKM-20260207-0001', '2026-02-07', 'masuk', 1, NULL, 3, 1, 1000000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Februari', NULL, 2, '2026-02-07 09:17:00'),
(19, 'BKK-20260209-0001', '2026-02-09', 'keluar', 1, NULL, 9, 2, 700000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-02-09 09:18:00'),
(20, 'BKM-20260209-0001', '2026-02-09', 'masuk', 2, NULL, 1, 2, 35100000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Februari', 'VA-0203', 2, '2026-02-09 09:19:00'),
(21, 'BKK-20260212-0001', '2026-02-12', 'keluar', 1, NULL, 13, 1, 1700000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Februari', NULL, 2, '2026-02-12 09:20:00'),
(22, 'BKM-20260212-0001', '2026-02-12', 'masuk', 2, NULL, 1, 2, 45650000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Februari', 'VA-0204', 2, '2026-02-12 09:21:00'),
(23, 'BKK-20260214-0001', '2026-02-14', 'keluar', 1, NULL, 14, 1, 1500000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-02-14 09:22:00'),
(24, 'BKK-20260216-0001', '2026-02-16', 'keluar', 2, NULL, 12, 7, 15400000, 'CV Karya Mandiri', 'Perbaikan atap dan instalasi listrik gedung kuliah', NULL, 2, '2026-02-16 09:23:00'),
(25, 'BKK-20260225-0001', '2026-02-25', 'keluar', 2, NULL, 7, 2, 92750000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Februari', NULL, 2, '2026-02-25 09:24:00'),
(26, 'BKK-20260228-0001', '2026-02-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Februari', NULL, 2, '2026-02-28 09:25:00'),
(27, 'TRF-20260302-0001', '2026-03-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Maret', NULL, 2, '2026-03-02 09:26:00'),
(28, 'BKM-20260303-0001', '2026-03-03', 'masuk', 2, NULL, 1, 2, 29900000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Maret', 'VA-0301', 2, '2026-03-03 09:27:00'),
(29, 'BKK-20260305-0001', '2026-03-05', 'keluar', 2, NULL, 8, 7, 4600000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Maret', NULL, 2, '2026-03-05 09:28:00'),
(30, 'BKM-20260306-0001', '2026-03-06', 'masuk', 2, NULL, 1, 2, 40050000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Maret', 'VA-0302', 2, '2026-03-06 09:29:00'),
(31, 'BKM-20260307-0001', '2026-03-07', 'masuk', 1, NULL, 3, 1, 2000000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Maret', NULL, 2, '2026-03-07 09:30:00'),
(32, 'BKK-20260309-0001', '2026-03-09', 'keluar', 1, NULL, 9, 2, 800000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-03-09 09:31:00'),
(33, 'BKM-20260309-0001', '2026-03-09', 'masuk', 2, NULL, 1, 2, 35050000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Maret', 'VA-0303', 2, '2026-03-09 09:32:00'),
(34, 'BKK-20260310-0001', '2026-03-10', 'keluar', 2, NULL, 10, 4, 18000000, 'Panitia Kegiatan', 'Workshop kurikulum OBE prodi', NULL, 2, '2026-03-10 09:33:00'),
(35, 'BKM-20260311-0001', '2026-03-11', 'masuk', 3, NULL, 4, 1, 150000000, 'Kementerian Agama RI', 'Bantuan Operasional Pendidikan (BOP) PTKIS Kemenag', 'SP2D-0317', 2, '2026-03-11 09:34:00'),
(36, 'BKK-20260312-0001', '2026-03-12', 'keluar', 1, NULL, 13, 1, 2700000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Maret', NULL, 2, '2026-03-12 09:35:00'),
(37, 'BKM-20260312-0001', '2026-03-12', 'masuk', 2, NULL, 1, 2, 45200000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Maret', 'VA-0304', 2, '2026-03-12 09:36:00'),
(38, 'BKK-20260314-0001', '2026-03-14', 'keluar', 1, NULL, 14, 1, 600000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-03-14 09:37:00'),
(39, 'BKK-20260322-0001', '2026-03-22', 'keluar', 3, NULL, 12, 7, 35000000, 'CV Media Edukasi', 'Pengadaan proyektor & sound system ruang kelas (dana BOP)', NULL, 2, '2026-03-22 09:38:00'),
(40, 'BKK-20260325-0001', '2026-03-25', 'keluar', 2, NULL, 7, 2, 93000000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Maret', NULL, 2, '2026-03-25 09:39:00'),
(41, 'BKK-20260328-0001', '2026-03-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Maret', NULL, 2, '2026-03-28 09:40:00'),
(42, 'TRF-20260402-0001', '2026-04-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan April', NULL, 2, '2026-04-02 09:41:00'),
(43, 'BKM-20260403-0001', '2026-04-03', 'masuk', 2, NULL, 1, 2, 37850000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan April', 'VA-0401', 2, '2026-04-03 09:42:00'),
(44, 'BKK-20260405-0001', '2026-04-05', 'keluar', 2, NULL, 8, 7, 4100000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet April', NULL, 2, '2026-04-05 09:43:00'),
(45, 'BKM-20260406-0001', '2026-04-06', 'masuk', 2, NULL, 1, 2, 33150000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan April', 'VA-0402', 2, '2026-04-06 09:44:00'),
(46, 'BKM-20260407-0001', '2026-04-07', 'masuk', 1, NULL, 3, 1, 3750000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan April', NULL, 2, '2026-04-07 09:45:00'),
(47, 'BKK-20260409-0001', '2026-04-09', 'keluar', 1, NULL, 9, 2, 1500000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-04-09 09:46:00'),
(48, 'BKM-20260409-0001', '2026-04-09', 'masuk', 2, NULL, 1, 2, 34550000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan April', 'VA-0403', 2, '2026-04-09 09:47:00'),
(49, 'BKK-20260412-0001', '2026-04-12', 'keluar', 1, NULL, 13, 1, 1100000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan April', NULL, 2, '2026-04-12 09:48:00'),
(50, 'BKM-20260412-0001', '2026-04-12', 'masuk', 2, NULL, 1, 2, 31850000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan April', 'VA-0404', 2, '2026-04-12 09:49:00'),
(51, 'BKK-20260414-0001', '2026-04-14', 'keluar', 1, NULL, 14, 1, 1400000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-04-14 09:50:00'),
(52, 'BKK-20260420-0001', '2026-04-20', 'keluar', 1, NULL, 11, 6, 6500000, 'BEM IMC', 'Kegiatan Ramadhan mahasiswa', NULL, 2, '2026-04-20 09:51:00'),
(53, 'BKK-20260425-0001', '2026-04-25', 'keluar', 2, NULL, 7, 2, 93250000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan April', NULL, 2, '2026-04-25 09:52:00'),
(54, 'BKK-20260428-0001', '2026-04-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank April', NULL, 2, '2026-04-28 09:53:00'),
(55, 'TRF-20260502-0001', '2026-05-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Mei', NULL, 2, '2026-05-02 09:54:00'),
(56, 'BKM-20260503-0001', '2026-05-03', 'masuk', 2, NULL, 1, 2, 34750000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Mei', 'VA-0501', 2, '2026-05-03 09:55:00'),
(57, 'BKK-20260505-0001', '2026-05-05', 'keluar', 2, NULL, 8, 7, 4300000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Mei', NULL, 2, '2026-05-05 09:56:00'),
(58, 'BKM-20260506-0001', '2026-05-06', 'masuk', 2, NULL, 1, 2, 45650000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Mei', 'VA-0502', 2, '2026-05-06 09:57:00'),
(59, 'BKM-20260507-0001', '2026-05-07', 'masuk', 1, NULL, 3, 1, 2000000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Mei', NULL, 2, '2026-05-07 09:58:00'),
(60, 'BKK-20260509-0001', '2026-05-09', 'keluar', 1, NULL, 9, 2, 800000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-05-09 09:59:00'),
(61, 'BKM-20260509-0001', '2026-05-09', 'masuk', 2, NULL, 1, 2, 38700000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Mei', 'VA-0503', 2, '2026-05-09 09:00:00'),
(62, 'BKK-20260512-0001', '2026-05-12', 'keluar', 1, NULL, 13, 1, 1700000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Mei', NULL, 2, '2026-05-12 09:01:00'),
(63, 'BKM-20260512-0001', '2026-05-12', 'masuk', 2, NULL, 1, 2, 42550000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Mei', 'VA-0504', 2, '2026-05-12 09:02:00'),
(64, 'BKK-20260514-0001', '2026-05-14', 'keluar', 1, NULL, 14, 1, 600000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-05-14 09:03:00'),
(65, 'BKK-20260516-0001', '2026-05-16', 'keluar', 2, NULL, 12, 7, 15300000, 'CV Karya Mandiri', 'Perbaikan atap dan instalasi listrik gedung kuliah', NULL, 2, '2026-05-16 09:04:00'),
(66, 'BKK-20260525-0001', '2026-05-25', 'keluar', 2, NULL, 7, 2, 93500000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Mei', NULL, 2, '2026-05-25 09:05:00'),
(67, 'BKM-20260526-0001', '2026-05-26', 'masuk', 2, NULL, 5, 3, 68500000, 'Wisudawan/wisudawati', 'Biaya wisuda angkatan XII', NULL, 2, '2026-05-26 09:06:00'),
(68, 'BKK-20260527-0001', '2026-05-27', 'keluar', 2, NULL, 10, 3, 38000000, 'Panitia Wisuda', 'Penyelenggaraan wisuda angkatan XII', NULL, 2, '2026-05-27 09:07:00'),
(69, 'BKK-20260528-0001', '2026-05-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Mei', NULL, 2, '2026-05-28 09:08:00'),
(70, 'TRF-20260602-0001', '2026-06-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Juni', NULL, 2, '2026-06-02 09:09:00'),
(71, 'BKM-20260603-0001', '2026-06-03', 'masuk', 2, NULL, 1, 2, 37800000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Juni', 'VA-0601', 2, '2026-06-03 09:10:00'),
(72, 'BKK-20260605-0001', '2026-06-05', 'keluar', 2, NULL, 8, 7, 4600000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Juni', NULL, 2, '2026-06-05 09:11:00'),
(73, 'BKM-20260606-0001', '2026-06-06', 'masuk', 2, NULL, 1, 2, 43500000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Juni', 'VA-0602', 2, '2026-06-06 09:12:00'),
(74, 'BKM-20260607-0001', '2026-06-07', 'masuk', 1, NULL, 3, 1, 3000000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Juni', NULL, 2, '2026-06-07 09:13:00'),
(75, 'BKM-20260608-0001', '2026-06-08', 'masuk', 2, NULL, 2, 3, 21300000, 'Calon mahasiswa baru', 'Pendaftaran mahasiswa baru gelombang 1', NULL, 2, '2026-06-08 09:14:00'),
(76, 'BKK-20260609-0001', '2026-06-09', 'keluar', 1, NULL, 9, 2, 800000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-06-09 09:15:00'),
(77, 'BKM-20260609-0001', '2026-06-09', 'masuk', 2, NULL, 1, 2, 42450000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Juni', 'VA-0603', 2, '2026-06-09 09:16:00'),
(78, 'BKK-20260612-0001', '2026-06-12', 'keluar', 1, NULL, 13, 1, 2000000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Juni', NULL, 2, '2026-06-12 09:17:00'),
(79, 'BKM-20260612-0001', '2026-06-12', 'masuk', 2, NULL, 1, 2, 30150000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Juni', 'VA-0604', 2, '2026-06-12 09:18:00'),
(80, 'BKK-20260614-0001', '2026-06-14', 'keluar', 1, NULL, 14, 1, 700000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-06-14 09:19:00'),
(81, 'BKK-20260615-0001', '2026-06-15', 'keluar', 3, NULL, 15, 3, 42000000, 'LPPM IMC', 'Hibah penelitian dosen internal tahap I', NULL, 2, '2026-06-15 09:20:00'),
(82, 'BKM-20260618-0001', '2026-06-18', 'masuk', 2, NULL, 2, 3, 19800000, 'Calon mahasiswa baru', 'Pendaftaran mahasiswa baru gelombang 1', NULL, 2, '2026-06-18 09:21:00'),
(83, 'BKK-20260625-0001', '2026-06-25', 'keluar', 2, NULL, 7, 2, 93750000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Juni', NULL, 2, '2026-06-25 09:22:00'),
(84, 'BKK-20260628-0001', '2026-06-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Juni', NULL, 2, '2026-06-28 09:23:00'),
(85, 'TRF-20260702-0001', '2026-07-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Juli', NULL, 2, '2026-07-02 09:24:00'),
(86, 'BKM-20260703-0001', '2026-07-03', 'masuk', 2, NULL, 1, 2, 29100000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Juli', 'VA-0701', 2, '2026-07-03 09:25:00'),
(87, 'BKM-20260704-0001', '2026-07-04', 'masuk', 2, NULL, 3, 1, 25000000, 'Ikatan Alumni IMC', 'Donasi alumni untuk renovasi masjid kampus', NULL, 2, '2026-07-04 09:26:00'),
(88, 'BKK-20260705-0001', '2026-07-05', 'keluar', 2, NULL, 8, 7, 4900000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Juli', NULL, 2, '2026-07-05 09:27:00'),
(89, 'BKM-20260706-0001', '2026-07-06', 'masuk', 2, NULL, 1, 2, 45900000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Juli', 'VA-0702', 2, '2026-07-06 09:28:00'),
(90, 'BKM-20260707-0001', '2026-07-07', 'masuk', 1, NULL, 3, 1, 2750000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Juli', NULL, 2, '2026-07-07 09:29:00'),
(91, 'BKM-20260708-0001', '2026-07-08', 'masuk', 2, NULL, 2, 3, 13500000, 'Calon mahasiswa baru', 'Pendaftaran mahasiswa baru gelombang 2', NULL, 2, '2026-07-08 09:30:00'),
(92, 'BKK-20260709-0001', '2026-07-09', 'keluar', 1, NULL, 9, 2, 1300000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-07-09 09:31:00'),
(93, 'BKM-20260709-0001', '2026-07-09', 'masuk', 2, NULL, 1, 2, 38500000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Juli', 'VA-0703', 2, '2026-07-09 09:32:00'),
(94, 'BKK-20260712-0001', '2026-07-12', 'keluar', 1, NULL, 13, 1, 1200000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Juli', NULL, 2, '2026-07-12 09:33:00'),
(95, 'BKM-20260712-0001', '2026-07-12', 'masuk', 2, NULL, 1, 2, 39950000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Juli', 'VA-0704', 2, '2026-07-12 09:34:00'),
(96, 'BKK-20260714-0001', '2026-07-14', 'keluar', 1, NULL, 14, 1, 1800000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-07-14 09:35:00'),
(97, 'BKK-20260718-0001', '2026-07-18', 'keluar', 2, NULL, 10, 3, 22500000, 'Panitia Ujian', 'Pelaksanaan UAS semester genap', NULL, 2, '2026-07-18 09:36:00'),
(98, 'BKM-20260718-0001', '2026-07-18', 'masuk', 2, NULL, 2, 3, 17100000, 'Calon mahasiswa baru', 'Pendaftaran mahasiswa baru gelombang 2', NULL, 2, '2026-07-18 09:37:00'),
(99, 'BKK-20260725-0001', '2026-07-25', 'keluar', 2, NULL, 7, 2, 94000000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Juli', NULL, 2, '2026-07-25 09:38:00'),
(100, 'BKK-20260728-0001', '2026-07-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Juli', NULL, 2, '2026-07-28 09:39:00'),
(101, 'TRF-20260802-0001', '2026-08-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan Agustus', NULL, 2, '2026-08-02 09:40:00'),
(102, 'BKM-20260803-0001', '2026-08-03', 'masuk', 2, NULL, 1, 2, 43100000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan Agustus', 'VA-0801', 2, '2026-08-03 09:41:00'),
(103, 'BKK-20260805-0001', '2026-08-05', 'keluar', 2, NULL, 8, 7, 4700000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet Agustus', NULL, 2, '2026-08-05 09:42:00'),
(104, 'BKM-20260806-0001', '2026-08-06', 'masuk', 2, NULL, 1, 2, 29450000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan Agustus', 'VA-0802', 2, '2026-08-06 09:43:00'),
(105, 'BKM-20260807-0001', '2026-08-07', 'masuk', 1, NULL, 3, 1, 1000000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan Agustus', NULL, 2, '2026-08-07 09:44:00'),
(106, 'BKM-20260808-0001', '2026-08-08', 'masuk', 2, NULL, 2, 3, 21300000, 'Calon mahasiswa baru', 'Pendaftaran mahasiswa baru gelombang 3', NULL, 2, '2026-08-08 09:45:00'),
(107, 'BKK-20260809-0001', '2026-08-09', 'keluar', 1, NULL, 9, 2, 1100000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-08-09 09:46:00'),
(108, 'BKM-20260809-0001', '2026-08-09', 'masuk', 2, NULL, 1, 2, 42450000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan Agustus', 'VA-0803', 2, '2026-08-09 09:47:00'),
(109, 'BKK-20260812-0001', '2026-08-12', 'keluar', 1, NULL, 13, 1, 1500000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan Agustus', NULL, 2, '2026-08-12 09:48:00'),
(110, 'BKM-20260812-0001', '2026-08-12', 'masuk', 2, NULL, 1, 2, 40550000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan Agustus', 'VA-0804', 2, '2026-08-12 09:49:00'),
(111, 'BKK-20260814-0001', '2026-08-14', 'keluar', 1, NULL, 14, 1, 1400000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-08-14 09:50:00'),
(112, 'BKK-20260816-0001', '2026-08-16', 'keluar', 2, NULL, 12, 7, 9400000, 'CV Karya Mandiri', 'Perbaikan atap dan instalasi listrik gedung kuliah', NULL, 2, '2026-08-16 09:51:00'),
(113, 'BKM-20260818-0001', '2026-08-18', 'masuk', 2, NULL, 2, 3, 12900000, 'Calon mahasiswa baru', 'Pendaftaran mahasiswa baru gelombang 3', NULL, 2, '2026-08-18 09:52:00'),
(114, 'BKK-20260820-0001', '2026-08-20', 'keluar', 2, NULL, 11, 6, 15000000, 'BEM IMC', 'PKKMB mahasiswa baru', NULL, 2, '2026-08-20 09:53:00'),
(115, 'BKK-20260825-0001', '2026-08-25', 'keluar', 2, NULL, 7, 2, 94250000, 'Dosen & tenaga kependidikan', 'Gaji & honorarium dosen dan tendik bulan Agustus', NULL, 2, '2026-08-25 09:54:00'),
(116, 'BKK-20260828-0001', '2026-08-28', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank Agustus', NULL, 2, '2026-08-28 09:55:00'),
(117, 'TRF-20260902-0001', '2026-09-02', 'transfer', 2, 1, NULL, NULL, 4000000, NULL, 'Pengisian kas kecil bulan September', NULL, 2, '2026-09-02 09:56:00'),
(118, 'BKM-20260903-0001', '2026-09-03', 'masuk', 2, NULL, 1, 2, 34450000, 'Mahasiswa angkatan 2022', 'Pembayaran UKT mahasiswa angkatan 2022 bulan September', 'VA-0901', 2, '2026-09-03 09:57:00'),
(119, 'BKM-20260903-0002', '2026-09-03', 'masuk', 1, NULL, 6, 7, 1750000, 'Karang Taruna Desa', 'Sewa aula untuk kegiatan masyarakat', NULL, 2, '2026-09-03 09:58:00'),
(120, 'BKK-20260905-0001', '2026-09-05', 'keluar', 2, NULL, 8, 7, 4700000, 'PLN, PDAM & ISP', 'Tagihan listrik, PDAM & internet September', NULL, 2, '2026-09-05 09:59:00'),
(121, 'BKM-20260906-0001', '2026-09-06', 'masuk', 2, NULL, 1, 2, 32350000, 'Mahasiswa angkatan 2023', 'Pembayaran UKT mahasiswa angkatan 2023 bulan September', 'VA-0902', 2, '2026-09-06 09:00:00'),
(122, 'BKM-20260907-0001', '2026-09-07', 'masuk', 1, NULL, 3, 1, 1500000, 'Jamaah & civitas akademika', 'Infaq Jumat & kotak amal kampus bulan September', NULL, 2, '2026-09-07 09:01:00'),
(123, 'BKK-20260909-0001', '2026-09-09', 'keluar', 1, NULL, 9, 2, 1200000, 'Toko Sumber Ilmu', 'Pembelian ATK & tinta printer', NULL, 2, '2026-09-09 09:02:00'),
(124, 'BKM-20260909-0001', '2026-09-09', 'masuk', 2, NULL, 1, 2, 40600000, 'Mahasiswa angkatan 2024', 'Pembayaran UKT mahasiswa angkatan 2024 bulan September', 'VA-0903', 2, '2026-09-09 09:03:00'),
(125, 'BKK-20260910-0001', '2026-09-10', 'keluar', 2, NULL, 10, 5, 12500000, 'Panitia Kegiatan', 'Kuliah umum & seminar nasional', NULL, 2, '2026-09-10 09:04:00'),
(126, 'BKK-20260912-0001', '2026-09-12', 'keluar', 1, NULL, 13, 1, 2700000, 'Catering Barokah', 'Konsumsi rapat pimpinan bulan September', NULL, 2, '2026-09-12 09:05:00'),
(127, 'BKM-20260912-0001', '2026-09-12', 'masuk', 2, NULL, 1, 2, 43100000, 'Mahasiswa angkatan 2025', 'Pembayaran UKT mahasiswa angkatan 2025 bulan September', 'VA-0904', 2, '2026-09-12 09:06:00'),
(128, 'BKK-20260914-0001', '2026-09-14', 'keluar', 1, NULL, 14, 1, 900000, 'Staf Rektorat', 'Transportasi koordinasi Kemenag Kab. Sukabumi', NULL, 2, '2026-09-14 09:07:00'),
(129, 'BKK-20260920-0001', '2026-09-20', 'keluar', 2, NULL, 11, 6, 15000000, 'BEM IMC', 'Musyawarah besar BEM', NULL, 2, '2026-09-20 09:08:00'),
(130, 'BKK-20260924-0001', '2026-09-24', 'keluar', 2, NULL, 16, 2, 150000, 'Bank Syariah Indonesia', 'Biaya administrasi bank September', NULL, 2, '2026-09-24 09:09:00');

INSERT INTO pengajuan (no_pengajuan, tanggal, unit_id, kategori_id, judul, rincian, jumlah, jumlah_disetujui, status, catatan, user_id, diputuskan_oleh, tgl_keputusan, transaksi_id) VALUES
('PGJ-20260302-0001', '2026-03-02', 4, 10, 'Workshop kurikulum OBE Prodi PAI', 'Narasumber 2 orang, konsumsi 60 peserta, penggandaan modul', 18000000, 18000000, 'dicairkan', 'Disetujui sesuai RAB', 4, 3, '2026-03-04 10:00:00', 34),
('PGJ-20260901-0001', '2026-09-01', 5, 10, 'Kuliah umum & seminar nasional MPI', 'Honor narasumber, sertifikat, konsumsi, dokumentasi', 14000000, 12500000, 'dicairkan', 'Disetujui dengan penyesuaian konsumsi', 2, 3, '2026-09-03 09:30:00', 125),
('PGJ-20260915-0001', '2026-09-15', 6, 11, 'Lomba karya tulis ilmiah mahasiswa', 'Hadiah juara, juri, sertifikat, publikasi', 7500000, 7000000, 'disetujui', 'Silakan koordinasi dengan bendahara', 2, 3, '2026-09-17 13:15:00', NULL),
('PGJ-20260918-0001', '2026-09-18', 4, 15, 'Pengabdian masyarakat di Desa Citepus', 'Transportasi, konsumsi, bahan pelatihan guru TPQ', 9500000, NULL, 'diajukan', NULL, 4, NULL, NULL, NULL),
('PGJ-20260921-0001', '2026-09-21', 7, 12, 'Pengecatan ulang gedung asrama', 'Cat 40 galon, upah tukang 6 orang x 10 hari', 28000000, NULL, 'diajukan', NULL, 2, NULL, NULL, NULL),
('PGJ-20260810-0001', '2026-08-10', 6, 11, 'Studi banding BEM ke Yogyakarta', 'Sewa bus, penginapan 3 malam', 32000000, NULL, 'ditolak', 'Belum tersedia anggaran, ajukan kembali semester depan', 2, 3, '2026-08-12 08:45:00', NULL);

UPDATE pengajuan SET created_at = CONCAT(tanggal, ' 09:00:00');

INSERT INTO log_aktivitas (user_id, aksi, detail, ip) VALUES (1, 'install', 'Database SIMKEU dipasang', '127.0.0.1');
