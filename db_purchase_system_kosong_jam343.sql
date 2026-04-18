-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 23, 2026 at 09:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_purchase_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `bon_permintaan`
--

CREATE TABLE `bon_permintaan` (
  `id_bon` int(11) NOT NULL,
  `no_permintaan` varchar(50) DEFAULT NULL,
  `id_barang` int(11) DEFAULT NULL,
  `tgl_keluar` timestamp NOT NULL DEFAULT current_timestamp(),
  `qty_keluar` int(11) DEFAULT NULL,
  `penerima` varchar(100) DEFAULT NULL,
  `keperluan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_retur`
--

CREATE TABLE `log_retur` (
  `id_log_retur` int(11) NOT NULL,
  `tgl_retur` datetime DEFAULT NULL,
  `no_request` varchar(50) DEFAULT NULL,
  `nama_barang_retur` varchar(255) DEFAULT NULL,
  `qty_retur` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `alokasi_sebelumnya` varchar(50) DEFAULT NULL,
  `alasan_retur` text DEFAULT NULL,
  `eksekutor_retur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_barang`
--

CREATE TABLE `master_barang` (
  `id_barang` int(11) NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `merk` varchar(50) DEFAULT NULL,
  `kategori` varchar(100) NOT NULL,
  `satuan` varchar(20) DEFAULT NULL,
  `stok_minimal` int(11) DEFAULT 3,
  `stok_akhir` decimal(10,2) DEFAULT 0.00,
  `lokasi_rak` varchar(50) DEFAULT NULL,
  `status_aktif` enum('AKTIF','NONAKTIF') DEFAULT 'AKTIF',
  `created_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_mobil`
--

CREATE TABLE `master_mobil` (
  `id_mobil` int(11) NOT NULL,
  `plat_nomor` varchar(20) NOT NULL,
  `driver_tetap` varchar(100) DEFAULT NULL,
  `jenis_kendaraan` varchar(50) DEFAULT NULL,
  `kategori_kendaraan` varchar(50) DEFAULT NULL,
  `merk_tipe` varchar(50) DEFAULT NULL,
  `tahun_kendaraan` year(4) DEFAULT NULL,
  `status_aktif` enum('AKTIF','NONAKTIF') DEFAULT 'AKTIF',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pembelian`
--

CREATE TABLE `pembelian` (
  `id_pembelian` int(11) NOT NULL,
  `id_request` int(11) DEFAULT NULL,
  `no_request` varchar(50) DEFAULT NULL,
  `tgl_beli` date NOT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `nama_barang_beli` varchar(150) NOT NULL,
  `merk_beli` varchar(100) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `harga` decimal(15,2) DEFAULT NULL,
  `kategori_beli` varchar(100) DEFAULT NULL,
  `alokasi_stok` enum('LANGSUNG PAKAI','MASUK STOK') DEFAULT 'LANGSUNG PAKAI',
  `nama_pemesan` varchar(100) DEFAULT NULL,
  `driver` varchar(100) DEFAULT NULL,
  `plat_nomor` varchar(20) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `id_user_beli` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_bongkaran`
--

CREATE TABLE `tr_bongkaran` (
  `id_bongkaran` int(11) NOT NULL,
  `tgl_bongkar` date DEFAULT NULL,
  `asal_bongkaran` varchar(100) DEFAULT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `qty_bongkar` int(11) DEFAULT NULL,
  `qty_sisa` int(11) DEFAULT NULL,
  `satuan_bongkar` varchar(20) DEFAULT NULL,
  `kondisi_barang` enum('BAGUS','PERBAIKAN','RUSAK') DEFAULT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_bongkaran_keluar`
--

CREATE TABLE `tr_bongkaran_keluar` (
  `id_keluar` int(11) NOT NULL,
  `id_bongkaran` int(11) DEFAULT NULL,
  `tgl_keluar` date DEFAULT NULL,
  `qty_keluar` int(11) DEFAULT NULL,
  `penerima` varchar(100) DEFAULT NULL,
  `keperluan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_koreksi`
--

CREATE TABLE `tr_koreksi` (
  `id_koreksi` int(11) NOT NULL,
  `tgl_koreksi` date NOT NULL,
  `id_barang` int(11) DEFAULT NULL,
  `stok_sebelum` decimal(10,2) DEFAULT NULL,
  `stok_sesudah` decimal(10,2) DEFAULT NULL,
  `selisih` int(11) DEFAULT NULL,
  `tipe_koreksi` varchar(20) DEFAULT NULL,
  `alasan_koreksi` text DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_pemusnahan`
--

CREATE TABLE `tr_pemusnahan` (
  `id_pemusnahan` int(11) NOT NULL,
  `no_pemusnahan` varchar(25) DEFAULT NULL,
  `tgl_pemusnahan` date NOT NULL,
  `id_barang` int(11) DEFAULT NULL,
  `qty_dimusnahkan` decimal(10,2) DEFAULT NULL,
  `satuan` varchar(20) DEFAULT NULL,
  `metode_pemusnahan` varchar(50) DEFAULT NULL,
  `nilai_jual_scrap` int(11) DEFAULT 0,
  `alasan_pemusnahan` text DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_request`
--

CREATE TABLE `tr_request` (
  `id_request` int(11) NOT NULL,
  `no_request` varchar(50) NOT NULL,
  `tgl_request` date NOT NULL,
  `nama_pemesan` varchar(100) NOT NULL,
  `status_request` enum('PENDING','PROSES BELI','SELESAI','DITOLAK') DEFAULT 'PENDING',
  `kategori_pr` varchar(20) DEFAULT 'KECIL',
  `status_approval` varchar(20) DEFAULT 'DISETUJUI',
  `catatan_pimpinan` text DEFAULT NULL,
  `tgl_approval` datetime DEFAULT NULL,
  `approve_by` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_request_detail`
--

CREATE TABLE `tr_request_detail` (
  `id_detail` int(11) NOT NULL,
  `id_request` int(11) NOT NULL,
  `nama_barang_manual` varchar(255) DEFAULT NULL,
  `id_barang` int(11) DEFAULT NULL,
  `id_mobil` int(11) DEFAULT 0,
  `jumlah` decimal(10,2) NOT NULL,
  `satuan` varchar(20) DEFAULT NULL,
  `harga_satuan_estimasi` decimal(15,2) DEFAULT 0.00,
  `status_item` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  `subtotal_estimasi` decimal(15,2) GENERATED ALWAYS AS (`jumlah` * `harga_satuan_estimasi`) VIRTUAL,
  `kategori_barang` varchar(100) DEFAULT NULL,
  `kwalifikasi` varchar(255) DEFAULT NULL,
  `tipe_request` enum('STOK','LANGSUNG') DEFAULT 'STOK'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_retur`
--

CREATE TABLE `tr_retur` (
  `id_retur` int(11) NOT NULL,
  `no_retur` varchar(20) DEFAULT NULL,
  `tgl_retur` date NOT NULL,
  `jenis_retur` varchar(50) DEFAULT NULL,
  `id_barang` int(11) DEFAULT NULL,
  `qty_retur` decimal(10,2) DEFAULT NULL,
  `alasan_retur` text DEFAULT NULL,
  `pengembali` varchar(100) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tr_stok_log`
--

CREATE TABLE `tr_stok_log` (
  `id_log` int(11) NOT NULL,
  `id_barang` int(11) NOT NULL,
  `tgl_log` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipe_transaksi` enum('MASUK','KELUAR','KOREKSI','RETUR') NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('administrator','admin_gudang','bagian_pembelian','manager') NOT NULL,
  `bagian` enum('Gudang','Pembelian','IT','Manager') DEFAULT NULL,
  `status_aktif` enum('AKTIF','NONAKTIF') DEFAULT 'AKTIF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `username`, `password`, `nama_lengkap`, `role`, `bagian`, `status_aktif`) VALUES
(1, 'gudang1', '$2y$10$2NzLlyB2rhn1Qbybta3GX.NCiDQS0zJWQj2UqoJyVt3d8mnwNyLg6', 'Admin Gudang 1', 'admin_gudang', 'Gudang', 'AKTIF'),
(2, 'gudang2', '$2y$10$Gdk4vNJqD4ZKi4rZy3i5iOOFRagnmaL185J9Z48rEt1SfMVvPtxe6', 'Admin Gudang 2', 'admin_gudang', 'Gudang', 'AKTIF'),
(3, 'gudang3', '$2y$10$CwvvNtxLtimOvzKGOirS0eWqtKuEmmAVCJTpLl4QXRybwRfDyO9kK', 'ADMIN GUDANG 3', 'admin_gudang', NULL, 'AKTIF'),
(4, 'gudang4', '$2y$10$Jnv80XfALfstKTuqjHwtSujwGELnU6b8..nlsPsVYZgDE7W3p.4l.', 'ADMIN GUDANG 4', 'admin_gudang', NULL, 'AKTIF'),
(5, 'beli1', '$2y$10$6K2Mi9kFAenL/.mnY4Oj2../kKDUYK9IjnznGW/1StYgNms2CMh.u', 'Admin Pembelian 1', 'bagian_pembelian', 'Pembelian', 'AKTIF'),
(6, 'beli2', '$2y$10$0lTNkdk98BEsyvbsulWVRepAAQaKUlXsqUwBZFMtzTnbz9LzEHqRC', 'PETUGAS PEMBELIAN 2', 'bagian_pembelian', NULL, 'AKTIF'),
(7, 'superadmin', '$2y$10$pzEy24ukOH1minCxBemwxuPPegvtnlZXt5.YQ4IVyRNPN/vbBa5N.', 'Super Administrator', 'administrator', 'IT', 'AKTIF'),
(8, 'manager_mcp', '$2y$10$5DWnH97N6XLpOY0jmVa2HeQh.xJRtFzeltyCDgKToi4FcQT9Tjtdu', 'Manager Mutiaracahaya Plastindo', 'manager', 'Manager', 'AKTIF'),
(9, 'tes1', '$2y$10$JHFrkcX4rRNmtm6mNRs.weDHocQFvmAi5vUWWovd9sxKMp8/g1ZU6', 'tes', 'admin_gudang', 'Gudang', 'AKTIF');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bon_permintaan`
--
ALTER TABLE `bon_permintaan`
  ADD PRIMARY KEY (`id_bon`),
  ADD KEY `fk_bon_barang` (`id_barang`);

--
-- Indexes for table `log_retur`
--
ALTER TABLE `log_retur`
  ADD PRIMARY KEY (`id_log_retur`);

--
-- Indexes for table `master_barang`
--
ALTER TABLE `master_barang`
  ADD PRIMARY KEY (`id_barang`);

--
-- Indexes for table `master_mobil`
--
ALTER TABLE `master_mobil`
  ADD PRIMARY KEY (`id_mobil`),
  ADD UNIQUE KEY `plat_nomor` (`plat_nomor`);

--
-- Indexes for table `pembelian`
--
ALTER TABLE `pembelian`
  ADD PRIMARY KEY (`id_pembelian`),
  ADD KEY `fk_beli_user` (`id_user_beli`);

--
-- Indexes for table `tr_bongkaran`
--
ALTER TABLE `tr_bongkaran`
  ADD PRIMARY KEY (`id_bongkaran`),
  ADD KEY `nama_barang` (`nama_barang`);

--
-- Indexes for table `tr_bongkaran_keluar`
--
ALTER TABLE `tr_bongkaran_keluar`
  ADD PRIMARY KEY (`id_keluar`),
  ADD KEY `fk_bongkar_keluar` (`id_bongkaran`);

--
-- Indexes for table `tr_koreksi`
--
ALTER TABLE `tr_koreksi`
  ADD PRIMARY KEY (`id_koreksi`),
  ADD KEY `id_barang` (`id_barang`),
  ADD KEY `tgl_koreksi` (`tgl_koreksi`);

--
-- Indexes for table `tr_pemusnahan`
--
ALTER TABLE `tr_pemusnahan`
  ADD PRIMARY KEY (`id_pemusnahan`),
  ADD KEY `fk_musnah_brg` (`id_barang`),
  ADD KEY `no_pemusnahan` (`no_pemusnahan`);

--
-- Indexes for table `tr_request`
--
ALTER TABLE `tr_request`
  ADD PRIMARY KEY (`id_request`),
  ADD UNIQUE KEY `no_request` (`no_request`);

--
-- Indexes for table `tr_request_detail`
--
ALTER TABLE `tr_request_detail`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `fk_req_detail` (`id_request`);

--
-- Indexes for table `tr_retur`
--
ALTER TABLE `tr_retur`
  ADD PRIMARY KEY (`id_retur`),
  ADD KEY `fk_retur_brg` (`id_barang`);

--
-- Indexes for table `tr_stok_log`
--
ALTER TABLE `tr_stok_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `fk_log_barang` (`id_barang`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bon_permintaan`
--
ALTER TABLE `bon_permintaan`
  MODIFY `id_bon` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_retur`
--
ALTER TABLE `log_retur`
  MODIFY `id_log_retur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_barang`
--
ALTER TABLE `master_barang`
  MODIFY `id_barang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_mobil`
--
ALTER TABLE `master_mobil`
  MODIFY `id_mobil` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pembelian`
--
ALTER TABLE `pembelian`
  MODIFY `id_pembelian` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tr_bongkaran`
--
ALTER TABLE `tr_bongkaran`
  MODIFY `id_bongkaran` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tr_bongkaran_keluar`
--
ALTER TABLE `tr_bongkaran_keluar`
  MODIFY `id_keluar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tr_koreksi`
--
ALTER TABLE `tr_koreksi`
  MODIFY `id_koreksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tr_pemusnahan`
--
ALTER TABLE `tr_pemusnahan`
  MODIFY `id_pemusnahan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tr_request`
--
ALTER TABLE `tr_request`
  MODIFY `id_request` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tr_request_detail`
--
ALTER TABLE `tr_request_detail`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tr_retur`
--
ALTER TABLE `tr_retur`
  MODIFY `id_retur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tr_stok_log`
--
ALTER TABLE `tr_stok_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bon_permintaan`
--
ALTER TABLE `bon_permintaan`
  ADD CONSTRAINT `fk_bon_barang` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`);

--
-- Constraints for table `pembelian`
--
ALTER TABLE `pembelian`
  ADD CONSTRAINT `fk_beli_user` FOREIGN KEY (`id_user_beli`) REFERENCES `users` (`id_user`);

--
-- Constraints for table `tr_bongkaran_keluar`
--
ALTER TABLE `tr_bongkaran_keluar`
  ADD CONSTRAINT `fk_bongkar_keluar` FOREIGN KEY (`id_bongkaran`) REFERENCES `tr_bongkaran` (`id_bongkaran`) ON DELETE CASCADE;

--
-- Constraints for table `tr_koreksi`
--
ALTER TABLE `tr_koreksi`
  ADD CONSTRAINT `fk_koreksi_brg` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`);

--
-- Constraints for table `tr_pemusnahan`
--
ALTER TABLE `tr_pemusnahan`
  ADD CONSTRAINT `fk_musnah_brg` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`);

--
-- Constraints for table `tr_request_detail`
--
ALTER TABLE `tr_request_detail`
  ADD CONSTRAINT `fk_req_detail` FOREIGN KEY (`id_request`) REFERENCES `tr_request` (`id_request`) ON DELETE CASCADE;

--
-- Constraints for table `tr_retur`
--
ALTER TABLE `tr_retur`
  ADD CONSTRAINT `fk_retur_brg` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`);

--
-- Constraints for table `tr_stok_log`
--
ALTER TABLE `tr_stok_log`
  ADD CONSTRAINT `fk_log_barang` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
