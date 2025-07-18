-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 18 Jul 2025 pada 05.40
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rhode`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `username`, `password`, `nama_lengkap`, `created_at`) VALUES
(1, 'admin', 'admin123', 'Administrator RHODE', '2025-07-11 14:08:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customer`
--

CREATE TABLE `customer` (
  `id_customer` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_telepon` varchar(15) NOT NULL,
  `alamat` text NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `customer`
--

INSERT INTO `customer` (`id_customer`, `nama_lengkap`, `email`, `no_telepon`, `alamat`, `username`, `password`, `created_at`) VALUES
(2, 'Andi Alfahmi', 'andialfahmi2004@gmial.com', '082292831308', 'BTN Medeka Block A. No 1 Kota Palopo', 'alfahmi', 'Andialfahmi2004', '2025-07-16 08:21:57'),
(3, 'arham', 'arham@gmail.com', '082222222222', 'Bandung (Balandai dekat gunung )', 'arham', 'Arham123', '2025-07-16 12:03:09'),
(4, 'Ainun', 'ainun@gmail.com', '082393120599', 'anggrek', 'Ainun', 'ainun', '2025-07-17 00:05:20'),
(5, 'Nanda', 'nanda@gmail.com', '082190359986', 'sungai preman', 'Nanda', 'nanda', '2025-07-17 00:06:15'),
(6, 'Rahmat', 'rahmat@gmail.com', '082353221149', 'Binturu', 'Rahmat', 'rahmat', '2025-07-17 00:07:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id_detail` int(11) NOT NULL,
  `id_pesanan` int(11) DEFAULT NULL,
  `id_produk` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id_detail`, `id_pesanan`, `id_produk`, `jumlah`, `harga`, `subtotal`) VALUES
(16, 15, 12, 1, 750000.00, 750000.00),
(17, 15, 17, 1, 1670000.00, 1670000.00),
(18, 15, 23, 1, 459999.00, 459999.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(1, 'Toko ainun', 'Toko kencantikan', '2025-07-11 14:08:05'),
(2, 'Toko Fahmi', 'toko kecantikan dan kesehatan', '2025-07-11 14:08:05'),
(3, 'Toko Nanda', 'toko kecantikan', '2025-07-11 14:08:05'),
(4, 'Toko Rahmat', 'Toko kecantikan', '2025-07-11 14:08:05'),
(5, 'Toko Arham', 'toko kecantikan', '2025-07-11 14:08:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `keranjang`
--

CREATE TABLE `keranjang` (
  `id_keranjang` int(11) NOT NULL,
  `id_customer` int(11) DEFAULT NULL,
  `id_produk` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `keranjang`
--

INSERT INTO `keranjang` (`id_keranjang`, `id_customer`, `id_produk`, `jumlah`, `created_at`) VALUES
(18, 3, 23, 1, '2025-07-16 18:50:16'),
(22, 4, 23, 1, '2025-07-17 00:15:11'),
(23, 4, 21, 1, '2025-07-17 00:15:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int(11) NOT NULL,
  `id_pesanan` int(11) DEFAULT NULL,
  `metode_pembayaran` enum('transfer','cash','ewallet') NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status_pembayaran` enum('menunggu','lunas','gagal') DEFAULT 'menunggu',
  `tanggal_bayar` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id_pembayaran`, `id_pesanan`, `metode_pembayaran`, `jumlah_bayar`, `bukti_pembayaran`, `status_pembayaran`, `tanggal_bayar`) VALUES
(15, 15, 'transfer', 2879999.00, '1752711057_Screenshot 2025-07-15 163537.png', 'menunggu', '2025-07-17 00:10:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `id_customer` int(11) DEFAULT NULL,
  `tanggal_pesan` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_harga` decimal(10,2) NOT NULL,
  `status_pesanan` enum('menunggu','dikonfirmasi','diproses','selesai','dibatalkan') DEFAULT 'menunggu',
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id_pesanan`, `id_customer`, `tanggal_pesan`, `total_harga`, `status_pesanan`, `catatan`) VALUES
(15, 4, '2025-07-17 00:10:00', 2879999.00, 'dikonfirmasi', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id_produk` int(11) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` decimal(10,2) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `id_kategori` int(11) DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id_produk`, `nama_produk`, `deskripsi`, `harga`, `stok`, `id_kategori`, `gambar`, `status`, `created_at`) VALUES
(10, 'Peptide Lip Tint', 'Limited edition shade', 200000.00, 5, 1, '1752688472_WhatsApp Image 2025-07-17 at 01.52.00.jpeg', 'aktif', '2025-07-16 17:54:32'),
(11, 'Pocket Blush', 'The natural flush', 270000.00, 9, 1, '1752688611_WhatsApp Image 2025-07-17 at 01.54.19.jpeg', 'aktif', '2025-07-16 17:56:51'),
(12, 'The Summer Kit', 'Tree summer essentials', 750000.00, 4, 1, '1752688735_WhatsApp Image 2025-07-17 at 01.56.18.jpeg', 'aktif', '2025-07-16 17:58:55'),
(13, 'Summer Lip Case', 'Limited Edition Shade (Iphone 16 Pro Max)', 400000.00, 5, 1, '1752688839_WhatsApp Image 2025-07-17 at 01.57.53.jpeg', 'aktif', '2025-07-16 18:00:39'),
(14, 'Glasing Mist', 'The hydrating face spray', 475000.00, 8, 2, '1752689027_WhatsApp Image 2025-07-17 at 02.01.16.jpeg', 'aktif', '2025-07-16 18:03:47'),
(15, 'Glazing Milk', 'The essential prep layer', 490000.00, 6, 2, '1752689163_WhatsApp Image 2025-07-17 at 02.03.34.jpeg', 'aktif', '2025-07-16 18:06:03'),
(16, 'Barrier Butter', 'The intensive moisture balm', 530000.00, 10, 2, '1752689286_WhatsApp Image 2025-07-17 at 02.05.51.jpeg', 'aktif', '2025-07-16 18:08:06'),
(17, 'The Rhode Kit', 'Four daily skin essentials', 1670000.00, 3, 2, '1752689458_WhatsApp Image 2025-07-17 at 02.07.56.jpeg', 'aktif', '2025-07-16 18:10:58'),
(18, 'Pocket Blush', 'The natural flush', 339999.00, 10, 3, '1752689564_WhatsApp Image 2025-07-17 at 02.10.51.jpeg', 'aktif', '2025-07-16 18:12:44'),
(19, 'Peptide Lip Shape', 'The contouring lip shaper', 499000.00, 9, 3, '1752689699_WhatsApp Image 2025-07-17 at 02.12.57.jpeg', 'aktif', '2025-07-16 18:14:59'),
(20, 'The summer Blush Duo', 'Tan line +Sun soak', 899000.00, 7, 4, '1752689957_WhatsApp Image 2025-07-17 at 02.17.08.jpeg', 'aktif', '2025-07-16 18:19:17'),
(21, 'The Glow Set', 'Glowy skin trio', 859999.00, 5, 4, '1752690073_WhatsApp Image 2025-07-17 at 02.19.15.jpeg', 'aktif', '2025-07-16 18:21:13'),
(22, 'Modern Make-up Must Have', 'Vogue beauty awards 2024', 399999.00, 8, 5, '1752690247_WhatsApp Image 2025-07-17 at 02.21.49.jpeg', 'aktif', '2025-07-16 18:24:07'),
(23, 'Best Cream Face Wash', 'Comsmopolitan beauty awards 2024', 459999.00, 5, 5, '1752690393_WhatsApp Image 2025-07-17 at 02.23.44.jpeg', 'aktif', '2025-07-16 18:26:33'),
(24, 'The Glow Set', 'Glowy skin trio', 839999.00, 6, 5, '1752713987_WhatsApp Image 2025-07-17 at 02.19.15.jpeg', 'aktif', '2025-07-17 00:59:47'),
(25, 'The summer Blush Duo', 'Tan line +Sun soak', 859999.00, 7, 5, '1752714065_WhatsApp Image 2025-07-17 at 02.17.08.jpeg', 'aktif', '2025-07-17 01:01:05'),
(26, 'The Summer Kit', 'Tree summer essentials', 799000.00, 8, 3, '1752714202_WhatsApp Image 2025-07-17 at 01.56.18.jpeg', 'aktif', '2025-07-17 01:03:22'),
(27, 'The Summer Kit', 'Tree summer essentials', 799000.00, 8, 3, '1752714390_WhatsApp Image 2025-07-17 at 01.56.18.jpeg', 'aktif', '2025-07-17 01:06:30');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id_customer`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `detail_pesanan_ibfk_2` (`id_produk`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id_keranjang`),
  ADD KEY `id_customer` (`id_customer`),
  ADD KEY `keranjang_ibfk_2` (`id_produk`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD KEY `id_customer` (`id_customer`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `customer`
--
ALTER TABLE `customer`
  MODIFY `id_customer` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id_keranjang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`),
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `keranjang_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customer` (`id_customer`),
  ADD CONSTRAINT `keranjang_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`);

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `customer` (`id_customer`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
