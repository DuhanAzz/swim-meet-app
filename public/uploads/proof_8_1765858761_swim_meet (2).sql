-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 16, 2025 at 03:43 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `swim_meet`
--

-- --------------------------------------------------------

--
-- Table structure for table `athlete_records`
--

CREATE TABLE `athlete_records` (
  `id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `nomor_lomba` varchar(50) NOT NULL,
  `waktu_terbaik` varchar(20) NOT NULL,
  `tanggal_dicapai` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `athlete_records`
--

INSERT INTO `athlete_records` (`id`, `swimmer_id`, `nomor_lomba`, `waktu_terbaik`, `tanggal_dicapai`, `created_at`) VALUES
(1, 2, '50m Gaya Bebas', '00.30.00', '2025-09-10', '2025-12-11 01:36:48'),
(2, 2, '100m Gaya Kupu', '01.00.00', '2025-09-10', '2025-12-11 01:36:53'),
(4, 8, '50m Gaya Bebas', '00.33.00', '2025-09-10', '2025-12-11 01:40:05'),
(8, 1, '50m Gaya Bebas', '00.33.00', '2025-09-10', '2025-12-11 01:49:54'),
(9, 6, '50m Gaya Bebas', '00.30.50', '2025-09-10', '2025-12-11 01:50:05'),
(10, 7, '50m Gaya Bebas', '00.29.00', '2025-12-11', '2025-12-11 01:50:18'),
(11, 3, '50m Gaya Bebas', '00.28.00', '2025-09-11', '2025-12-11 01:50:33'),
(12, 5, '50m Gaya Bebas', '00.26.00', '2025-09-11', '2025-12-11 01:50:45'),
(13, 4, '50m Gaya Bebas', '00.25.00', '2025-09-11', '2025-12-11 01:51:01'),
(15, 10, '50m Gaya Bebas', '00.33.00', '2025-09-10', '2025-12-12 03:17:08'),
(16, 14, '50m Gaya Bebas', '00.30.50', '2025-10-12', '2025-12-12 03:17:17'),
(17, 11, '50m Gaya Bebas', '00.29.50', '2025-09-12', '2025-12-12 03:17:30'),
(18, 13, '50m Gaya Bebas', '00.28.50', '2025-09-12', '2025-12-12 03:17:45'),
(19, 12, '50m Gaya Bebas', '00.29.90', '2025-09-12', '2025-12-12 03:17:57'),
(20, 14, '50m Gaya Dada', '00.35.00', '2025-09-12', '2025-12-12 03:47:06');

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_klub` varchar(100) NOT NULL,
  `kode_klub` varchar(50) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `singkatan` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `user_id`, `nama_klub`, `kode_klub`, `kota`, `logo`, `created_at`, `updated_at`, `singkatan`) VALUES
(1, 1, 'Millennium Aquatic', 'MNA', 'Jakarta', NULL, '2025-12-08 02:40:34', '2025-12-09 07:12:43', NULL),
(3, 8, 'Pari Sakti Swimming Club Yogyakarta Swimming Club', 'CLUB-938', 'Indonesia', NULL, '2025-12-09 07:19:05', '2025-12-09 07:19:05', 'STD'),
(4, 9, 'Universitas Negeri Yogyakarta Swimming Team', NULL, 'Yogyakarta', 'img/logos/logo_9_1765373933.png', '2025-12-10 05:07:02', '2025-12-10 13:38:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `judul_file` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `kategori` enum('buku_acara','buku_hasil','lainnya') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `event_id`, `judul_file`, `file_path`, `kategori`, `created_at`) VALUES
(1, 7, 2, 'Result Race Number', 'uploads/doc_1765343443.txt', 'buku_hasil', '2025-12-10 05:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `entries`
--

CREATE TABLE `entries` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `seed_time` varchar(20) DEFAULT '00:00.00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `nomor_acara` int(5) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `nama_event` varchar(100) NOT NULL,
  `jarak` int(11) NOT NULL,
  `gaya` varchar(50) NOT NULL,
  `jenis_kelamin` enum('L','P','Campuran') NOT NULL,
  `batas_umur_bawah` int(11) DEFAULT 0,
  `batas_umur_atas` int(11) DEFAULT 99,
  `biaya_pendaftaran` decimal(10,0) DEFAULT 25000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tanggal_lomba` date DEFAULT curdate(),
  `harga_pendaftaran` decimal(15,2) DEFAULT 0.00,
  `jam_lomba` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `nomor_acara`, `user_id`, `nama_event`, `jarak`, `gaya`, `jenis_kelamin`, `batas_umur_bawah`, `batas_umur_atas`, `biaya_pendaftaran`, `created_at`, `updated_at`, `tanggal_lomba`, `harga_pendaftaran`, `jam_lomba`) VALUES
(1, NULL, 2, '50m Gaya Bebas Putra', 50, 'Bebas', 'L', 0, 99, 25000, '2025-12-08 02:39:51', '2025-12-09 03:55:55', '2025-12-08', 0.00, '08:00:00'),
(2, 101, 7, '101 - 50M Gaya Bebas - KU 17-99', 50, 'Gaya Bebas', 'L', 17, 99, 25000, '2025-12-09 04:50:42', '2025-12-09 04:51:21', '2025-12-20', 10000.00, '08:00:00'),
(3, 102, 7, '102 - 50M Gaya Bebas - KU 17-99', 50, 'Gaya Bebas', 'P', 17, 99, 25000, '2025-12-09 04:52:11', '2025-12-09 04:52:11', '2025-12-20', 10000.00, '08:00:00'),
(4, 201, 7, '201 - 100M Gaya Kupu-kupu - KU 17-99', 100, 'Gaya Kupu-kupu', 'L', 17, 99, 25000, '2025-12-09 04:53:52', '2025-12-09 04:54:15', '2025-12-21', 10000.00, '08:00:00'),
(5, 202, 7, '202 - 100M Gaya Kupu-kupu - KU 17-99', 100, 'Gaya Kupu-kupu', 'P', 17, 99, 25000, '2025-12-09 04:54:50', '2025-12-09 04:54:50', '2025-12-21', 10000.00, '08:00:00'),
(6, 103, 7, '103 - 200M Gaya Ganti - KU 17-99', 200, 'Gaya Ganti', 'L', 17, 99, 25000, '2025-12-09 11:06:17', '2025-12-09 11:06:17', '2025-12-20', 10000.00, '08:00:00'),
(7, 104, 7, '104 - 200M Gaya Ganti - KU 17-99', 200, 'Gaya Ganti', 'P', 17, 99, 25000, '2025-12-09 11:07:52', '2025-12-09 11:07:52', '2025-12-20', 10000.00, '08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `event_age_groups`
--

CREATE TABLE `event_age_groups` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `min_age` int(11) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_no` int(11) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `gender` enum('Male','Female','Mixed') NOT NULL,
  `distance` int(11) NOT NULL,
  `style` varchar(50) NOT NULL,
  `age_group` varchar(50) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_categories`
--

INSERT INTO `event_categories` (`id`, `user_id`, `event_no`, `event_date`, `gender`, `distance`, `style`, `age_group`, `price`, `created_at`) VALUES
(7, 7, 101, '2026-08-20', 'Male', 50, 'Gaya Bebas', 'KU 1+', 100000.00, '2025-12-13 07:17:12'),
(8, 7, 102, '2026-08-20', 'Female', 50, 'Gaya Bebas', 'KU 1+', 100000.00, '2025-12-13 07:17:46'),
(9, 7, 103, '2026-08-20', 'Male', 100, 'Gaya Kupu-kupu', 'KU 1+', 100000.00, '2025-12-13 07:18:04'),
(11, 7, 104, '2026-08-20', 'Female', 100, 'Gaya Kupu-kupu', 'KU 1+', 100000.00, '2025-12-14 04:42:36');

-- --------------------------------------------------------

--
-- Table structure for table `event_entries`
--

CREATE TABLE `event_entries` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `entry_time` varchar(20) DEFAULT '00:00.00',
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_entries`
--

INSERT INTO `event_entries` (`id`, `event_id`, `club_id`, `swimmer_id`, `category_id`, `entry_time`, `status`, `created_at`) VALUES
(1, 7, 8, 2, 7, '00.30.00', 'Approved', '2025-12-16 02:03:59'),
(2, 7, 8, 2, 9, '99.99.99', 'Approved', '2025-12-16 02:03:59'),
(3, 7, 8, 3, 7, '00.28.00', 'Approved', '2025-12-16 02:04:06'),
(4, 7, 8, 3, 9, '99.99.99', 'Approved', '2025-12-16 02:04:06'),
(5, 7, 8, 7, 7, '00.29.00', 'Approved', '2025-12-16 02:04:15'),
(6, 7, 8, 7, 9, '99.99.99', 'Approved', '2025-12-16 02:04:15'),
(7, 7, 8, 6, 7, '00.30.50', 'Approved', '2025-12-16 02:04:21'),
(8, 7, 8, 6, 9, '99.99.99', 'Approved', '2025-12-16 02:04:21'),
(9, 7, 8, 5, 7, '00.26.00', 'Approved', '2025-12-16 02:04:26'),
(10, 7, 8, 5, 9, '99.99.99', 'Approved', '2025-12-16 02:04:26'),
(11, 7, 8, 4, 7, '00.25.00', 'Approved', '2025-12-16 02:04:30'),
(12, 7, 8, 4, 9, '99.99.99', 'Approved', '2025-12-16 02:04:30'),
(13, 7, 8, 8, 7, '00.33.00', 'Approved', '2025-12-16 02:04:34'),
(14, 7, 8, 8, 9, '99.99.99', 'Approved', '2025-12-16 02:04:34'),
(15, 7, 9, 11, 7, '00.29.50', 'Approved', '2025-12-16 02:07:19'),
(16, 7, 9, 10, 7, '00.33.00', 'Approved', '2025-12-16 02:07:22'),
(17, 7, 9, 12, 7, '00.29.90', 'Approved', '2025-12-16 02:07:25'),
(18, 7, 9, 9, 7, '99.99.99', 'Approved', '2025-12-16 02:07:28'),
(19, 7, 10, 14, 7, '00.30.50', 'Approved', '2025-12-16 02:09:19'),
(20, 7, 10, 17, 7, '99.99.99', 'Approved', '2025-12-16 02:09:23'),
(21, 7, 10, 16, 7, '99.99.99', 'Approved', '2025-12-16 02:09:28'),
(22, 7, 10, 13, 7, '00.28.50', 'Approved', '2025-12-16 02:09:32'),
(23, 7, 10, 15, 7, '99.99.99', 'Approved', '2025-12-16 02:09:35'),
(24, 7, 11, 18, 7, '99.99.99', 'Approved', '2025-12-16 02:11:44'),
(25, 7, 11, 20, 7, '99.99.99', 'Approved', '2025-12-16 02:11:47'),
(26, 7, 11, 19, 7, '99.99.99', 'Approved', '2025-12-16 02:11:50'),
(27, 7, 11, 21, 7, '99.99.99', 'Approved', '2025-12-16 02:11:53');

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_organizer_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_payments`
--

CREATE TABLE `event_payments` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `proof_file` varchar(255) NOT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `proof_image` varchar(255) DEFAULT NULL,
  `requirement_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_payments`
--

INSERT INTO `event_payments` (`id`, `event_id`, `club_id`, `total_amount`, `proof_file`, `status`, `created_at`, `proof_image`, `requirement_file`, `notes`) VALUES
(1, 7, 8, 800000.00, 'uploads/proof_8_1765612298_doc_7_1765347172 (3).pdf', 'Verified', '2025-12-13 07:51:38', NULL, 'uploads/req_8_1765612298_doc_7_1765347172 (2).pdf', NULL),
(2, 7, 10, 500000.00, 'uploads/proof_10_1765613598_doc_7_1765347172 (2).pdf', 'Verified', '2025-12-13 08:13:18', NULL, 'uploads/req_10_1765613598_doc_7_1765347172 (3).pdf', NULL),
(3, 7, 9, 300000.00, 'uploads/proof_9_1765685000_full book.pdf', 'Verified', '2025-12-14 04:03:20', NULL, 'uploads/req_9_1765685000_full book.pdf', NULL),
(4, 7, 8, 2200000.00, 'uploads/proof_8_1765849702_Screenshot 2025-12-15 at 8.50.57 PM.png', 'Verified', '2025-12-16 01:48:22', NULL, 'uploads/req_8_1765849702_Screenshot 2025-12-12 at 10.35.07 AM.png', NULL),
(5, 7, 9, 1400000.00, 'uploads/proof_9_1765849754_Screenshot 2025-12-12 at 10.35.07 AM.png', 'Verified', '2025-12-16 01:49:14', NULL, 'uploads/req_9_1765849754_Screenshot 2025-12-12 at 10.35.07 AM.png', NULL),
(6, 7, 10, 1000000.00, 'uploads/proof_10_1765849872_Screenshot 2025-12-15 at 8.50.57 PM.png', 'Verified', '2025-12-16 01:51:12', NULL, 'uploads/req_10_1765849872_Screenshot 2025-12-14 at 12.25.14 PM.png', NULL),
(7, 7, 8, 1400000.00, 'uploads/proof_8_1765850690_Screenshot 2025-12-15 at 8.50.57 PM.png', 'Verified', '2025-12-16 02:04:50', NULL, 'uploads/req_8_1765850690_Screenshot 2025-12-13 at 12.24.12 PM.png', NULL),
(8, 7, 9, 400000.00, 'uploads/proof_9_1765850859_Screenshot 2025-12-10 at 8.38.34 PM.png', 'Verified', '2025-12-16 02:07:39', NULL, 'uploads/req_9_1765850859_Screenshot 2025-12-13 at 12.24.12 PM.png', NULL),
(9, 7, 10, 500000.00, 'uploads/proof_10_1765850986_Screenshot 2025-12-10 at 8.38.34 PM.png', 'Verified', '2025-12-16 02:09:46', NULL, 'uploads/req_10_1765850986_Screenshot 2025-12-13 at 12.24.12 PM.png', NULL),
(10, 7, 11, 400000.00, 'uploads/proof_11_1765851123_Screenshot 2025-12-15 at 8.50.57 PM.png', 'Verified', '2025-12-16 02:12:04', NULL, 'uploads/req_11_1765851123_Screenshot 2025-12-10 at 8.38.34 PM.png', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_results`
--

CREATE TABLE `event_results` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT 'Result',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_results`
--

INSERT INTO `event_results` (`id`, `event_id`, `file_name`, `file_path`, `category`, `created_at`) VALUES
(1, 7, 'Race Result Number 101.pdf', 'uploads/results/doc_7_1765347172.pdf', 'Result', '2025-12-10 06:12:52'),
(2, 7, 'Juknis GSC', 'uploads/documents/DOC_Other_1765520549_7.pdf', 'Other', '2025-12-12 06:22:29'),
(3, 7, 'buku acara full gsc', 'uploads/documents/DOC_StartList_1765520583_7.pdf', 'StartList', '2025-12-12 06:23:03');

-- --------------------------------------------------------

--
-- Table structure for table `event_sponsors`
--

CREATE TABLE `event_sponsors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_sponsors`
--

INSERT INTO `event_sponsors` (`id`, `user_id`, `image_path`, `created_at`) VALUES
(1, 7, 'uploads/logos/sp_7_1765616232.png', '2025-12-13 08:57:12'),
(2, 7, 'uploads/logos/sp_7_1765616452.png', '2025-12-13 09:00:52');

-- --------------------------------------------------------

--
-- Table structure for table `heats`
--

CREATE TABLE `heats` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `heat_number` int(11) NOT NULL,
  `start_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `heats`
--

INSERT INTO `heats` (`id`, `event_id`, `heat_number`, `start_time`) VALUES
(3, 1, 1, NULL),
(10, 2, 1, NULL),
(11, 2, 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `heat_entries`
--

CREATE TABLE `heat_entries` (
  `id` int(11) NOT NULL,
  `heat_id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `lane_number` int(11) NOT NULL,
  `final_time` varchar(20) DEFAULT NULL,
  `status` enum('OK','DQ','DNS','DNF') DEFAULT 'OK',
  `rank` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hero_images`
--

CREATE TABLE `hero_images` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hero_images`
--

INSERT INTO `hero_images` (`id`, `image_path`, `created_at`) VALUES
(1, 'https://images.unsplash.com/photo-1530549387789-4c1017266635?ixlib=rb-4.0.3&auto=format&fit=crop&w=1740&q=80', '2025-12-08 14:06:12'),
(2, 'img/hero/hero_1765203416_0.jpg', '2025-12-08 14:16:56'),
(3, 'img/hero/hero_1765203427_0.jpg', '2025-12-08 14:17:07');

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

CREATE TABLE `hero_slides` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT '',
  `subtitle` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hero_slides`
--

INSERT INTO `hero_slides` (`id`, `image_path`, `title`, `subtitle`, `created_at`) VALUES
(1, 'img/hero/tix_slide_1765340189_697.jpg', 'Gadjah Mada Swimming Competition 2026', 'Tanggal 20 Agustus 2026', '2025-12-10 04:16:29'),
(2, 'img/hero/slide_1765341732_640.jpg', '', '', '2025-12-10 04:42:12');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `judul_tagihan` varchar(100) DEFAULT NULL,
  `jumlah_tagihan` decimal(15,2) DEFAULT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `tgl_bayar` datetime DEFAULT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `catatan_admin` text DEFAULT NULL,
  `tanggal_terbit` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `club_id`, `admin_id`, `judul_tagihan`, `jumlah_tagihan`, `bukti_bayar`, `tgl_bayar`, `status`, `catatan_admin`, `tanggal_terbit`) VALUES
(1, 3, 7, 'Pendaftaran Gadjah Mada Swimming Competition 2026', 110000.00, 'img/receipts/receipt_1_1765279038.png', '2025-12-09 18:17:18', 'paid', NULL, '2025-12-09'),
(2, 3, 7, 'Pendaftaran Gadjah Mada Swimming Competition 2026', 120000.00, 'img/receipts/receipt_2_1765280458.png', '2025-12-09 18:40:58', 'paid', NULL, '2025-12-09'),
(3, 3, 7, 'Pendaftaran Gadjah Mada Swimming Competition 2026', 130000.00, 'img/receipts/receipt_3_1765374236.png', '2025-12-10 20:43:56', 'paid', NULL, '2025-12-10'),
(4, 3, 7, 'Pendaftaran Gadjah Mada Swimming Competition 2026', 130000.00, NULL, NULL, 'unpaid', NULL, '2025-12-10');

-- --------------------------------------------------------

--
-- Table structure for table `meets`
--

CREATE TABLE `meets` (
  `id` bigint(20) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `race_heats`
--

CREATE TABLE `race_heats` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `heat_number` int(11) NOT NULL,
  `start_time` time DEFAULT NULL,
  `status` enum('Pending','Live','Finished') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `stage` enum('Prelims','Final') DEFAULT 'Prelims'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `race_heats`
--

INSERT INTO `race_heats` (`id`, `category_id`, `heat_number`, `start_time`, `status`, `created_at`, `stage`) VALUES
(20, 7, 1, NULL, 'Pending', '2025-12-16 02:37:56', 'Prelims'),
(21, 7, 2, NULL, 'Pending', '2025-12-16 02:37:56', 'Prelims'),
(22, 7, 3, NULL, 'Pending', '2025-12-16 02:37:56', 'Prelims');

-- --------------------------------------------------------

--
-- Table structure for table `race_lines`
--

CREATE TABLE `race_lines` (
  `id` int(11) NOT NULL,
  `heat_id` int(11) NOT NULL,
  `lane_number` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `entry_time` varchar(20) DEFAULT 'NT',
  `result_time` varchar(20) DEFAULT NULL,
  `status` varchar(10) DEFAULT 'OK',
  `rank` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `race_lines`
--

INSERT INTO `race_lines` (`id`, `heat_id`, `lane_number`, `swimmer_id`, `entry_time`, `result_time`, `status`, `rank`) VALUES
(128, 20, 4, 4, '00.25.00', NULL, 'OK', NULL),
(129, 20, 5, 5, '00.26.00', NULL, 'OK', NULL),
(130, 20, 3, 3, '00.28.00', NULL, 'OK', NULL),
(131, 20, 6, 13, '00.28.50', NULL, 'OK', NULL),
(132, 21, 4, 7, '00.29.00', NULL, 'OK', NULL),
(133, 21, 5, 11, '00.29.50', NULL, 'OK', NULL),
(134, 21, 3, 12, '00.29.90', NULL, 'OK', NULL),
(135, 21, 6, 2, '00.30.00', NULL, 'OK', NULL),
(136, 21, 2, 6, '00.30.50', NULL, 'OK', NULL),
(137, 21, 7, 14, '00.30.50', NULL, 'OK', NULL),
(138, 21, 1, 8, '00.33.00', NULL, 'OK', NULL),
(139, 21, 8, 10, '00.33.00', NULL, 'OK', NULL),
(140, 22, 4, 9, '99.99.99', NULL, 'OK', NULL),
(141, 22, 5, 17, '99.99.99', NULL, 'OK', NULL),
(142, 22, 3, 16, '99.99.99', NULL, 'OK', NULL),
(143, 22, 6, 15, '99.99.99', NULL, 'OK', NULL),
(144, 22, 2, 18, '99.99.99', NULL, 'OK', NULL),
(145, 22, 7, 20, '99.99.99', NULL, 'OK', NULL),
(146, 22, 1, 19, '99.99.99', NULL, 'OK', NULL),
(147, 22, 8, 21, '99.99.99', NULL, 'OK', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` bigint(20) NOT NULL,
  `meet_id` bigint(20) DEFAULT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `swimmer_id` bigint(20) DEFAULT NULL,
  `seed_time` varchar(50) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `hero_title` varchar(255) DEFAULT 'Sistem Manajemen Lomba Renang',
  `hero_subtitle` text DEFAULT NULL,
  `hero_image` varchar(255) DEFAULT 'https://images.unsplash.com/photo-1530549387789-4c1017266635',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `running_text` text DEFAULT NULL,
  `hero_title_2` varchar(255) DEFAULT 'Kompetisi Renang Nasional',
  `hero_subtitle_2` varchar(255) DEFAULT 'Daftarkan atlet terbaikmu sekarang juga.',
  `hero_image_2` varchar(255) DEFAULT '',
  `hero_title_3` varchar(255) DEFAULT 'Live Result Realtime',
  `hero_subtitle_3` varchar(255) DEFAULT 'Pantau perolehan waktu dan medali secara langsung.',
  `hero_image_3` varchar(255) DEFAULT '',
  `info_title` varchar(255) DEFAULT 'OPEN REGISTRATION',
  `info_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `hero_title`, `hero_subtitle`, `hero_image`, `updated_at`, `running_text`, `hero_title_2`, `hero_subtitle_2`, `hero_image_2`, `hero_title_3`, `hero_subtitle_3`, `hero_image_3`, `info_title`, `info_text`) VALUES
(1, 'welcome', 'Platform resmi pengelolaan data atlet dan hasil perlombaan renang profesional.', 'https://images.unsplash.com/photo-1530549387789-4c1017266635', '2025-12-10 04:50:46', 'Pendaftaran GSC 2026 telah di buka', 'Kompetisi Renang Nasional', 'Daftarkan atlet terbaikmu sekarang juga.', '', 'Live Result Realtime', 'Pantau perolehan waktu dan medali secara langsung.', '', 'Sports Entry Tech System ', 'Platform Web Sport Managemen dan Perfom Analisis terbaik');

-- --------------------------------------------------------

--
-- Table structure for table `swimmers`
--

CREATE TABLE `swimmers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_atlet` varchar(255) NOT NULL,
  `asal_sekolah` varchar(100) DEFAULT NULL,
  `jenis_kelamin` enum('Male','Female') NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `swimmers`
--

INSERT INTO `swimmers` (`id`, `user_id`, `nama_atlet`, `asal_sekolah`, `jenis_kelamin`, `tanggal_lahir`, `created_at`) VALUES
(2, 8, 'ARUFFIN BIN ABDUL SALAM', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:01:58'),
(3, 8, 'ARUFFIN BIN ABDUL SALAM', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:02:29'),
(4, 8, 'ISMAIL BIN MAIL', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:02:40'),
(5, 8, 'IJAT', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:03:02'),
(6, 8, 'EHSAN BIN AZZARUDIN', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:03:15'),
(7, 8, 'DZULKIFLI', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:03:29'),
(8, 8, 'MOHAMMAD AL-HAFEZZY', 'TADIKA MESRA', 'Male', '2002-01-01', '2025-12-16 02:03:48'),
(9, 9, 'LEVI AKERMAN', 'UNIVERSITAS NEGERI YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:06:06'),
(10, 9, 'EREN YEAGER', 'UNIVERSITAS NEGERI YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:06:19'),
(11, 9, 'ARMIN ALERT', 'UNIVERSITAS NEGERI YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:06:36'),
(12, 9, 'ERWIN SMITH', 'UNIVERSITAS NEGERI YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:07:03'),
(13, 10, 'DONTOL', 'SD KEPUTRAN 1 KOTA YK', 'Male', '2002-01-01', '2025-12-16 02:08:03'),
(14, 10, 'ADIT', 'SD KEPUTRAN 1 KOTA YK', 'Male', '2002-01-01', '2025-12-16 02:08:16'),
(15, 10, 'SOPO', 'SD KEPUTRAN 1 KOTA YK', 'Male', '2002-01-01', '2025-12-16 02:08:27'),
(16, 10, 'JARWO', 'SD KEPUTRAN 1 KOTA YK', 'Male', '2002-01-01', '2025-12-16 02:08:43'),
(17, 10, 'DENIS', 'SD KEPUTRAN 1 KOTA YK', 'Male', '2002-01-01', '2025-12-16 02:09:03'),
(18, 11, 'DORAEMON', 'UNIVERSITAS ATMA JAYA YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:10:37'),
(19, 11, 'NOBITA', 'UNIVERSITAS ATMA JAYA YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:10:51'),
(20, 11, 'GIANT', 'UNIVERSITAS ATMA JAYA YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:11:12'),
(21, 11, 'SUNEO', 'UNIVERSITAS ATMA JAYA YOGYAKARTA', 'Male', '2002-01-01', '2025-12-16 02:11:31');

-- --------------------------------------------------------

--
-- Table structure for table `swimmer_events`
--

CREATE TABLE `swimmer_events` (
  `id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `entry_time` varchar(20) DEFAULT 'NT',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `swimmer_records`
--

CREATE TABLE `swimmer_records` (
  `id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `nama_event` varchar(100) NOT NULL,
  `nomor_lomba` varchar(50) NOT NULL,
  `waktu` varchar(20) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('master','admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_date_display` varchar(100) DEFAULT NULL COMMENT 'Teks tanggal misal: 20-24 Agustus 2024',
  `event_start_date` date DEFAULT NULL COMMENT 'Untuk sorting upcoming/finished',
  `event_end_date` date DEFAULT NULL,
  `venue_name` varchar(150) DEFAULT NULL COMMENT 'Nama Kolam Renang',
  `location` varchar(150) DEFAULT NULL COMMENT 'Daerah/Kota',
  `event_image` varchar(255) DEFAULT NULL COMMENT 'Path gambar pamflet/logo event',
  `profile_image` varchar(255) DEFAULT NULL,
  `lane_count` int(11) DEFAULT 8,
  `competition_system` enum('Langsung Final','Penyisihan') DEFAULT 'Langsung Final',
  `age_calculation_type` enum('Dec 31','Meet Start') DEFAULT 'Dec 31',
  `seeding_type` enum('Spearhead','Slowest to Fastest') DEFAULT 'Spearhead',
  `event_type` enum('Langsung Final','Babak Penyisihan') DEFAULT 'Langsung Final',
  `event_status` enum('Registration','Running','Finished') DEFAULT 'Registration',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `logo_left` varchar(255) DEFAULT NULL,
  `logo_right` varchar(255) DEFAULT NULL,
  `logo_sponsor` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `photo`, `password`, `nama_lengkap`, `role`, `created_at`, `event_date_display`, `event_start_date`, `event_end_date`, `venue_name`, `location`, `event_image`, `profile_image`, `lane_count`, `competition_system`, `age_calculation_type`, `seeding_type`, `event_type`, `event_status`, `bank_name`, `bank_account_number`, `bank_account_name`, `logo_left`, `logo_right`, `logo_sponsor`) VALUES
(1, 'master', 'masterset@setsystem.id', 'img/users/user_1_1765336304.png', '$2y$10$yaAh1QQbp2H4nYSvqNtmwu4tNHpwkd9dg7XX0wan57Agf90BIkk3e', 'Master of Sports Entry System', 'master', '2025-12-08 03:11:07', NULL, NULL, NULL, NULL, NULL, NULL, 'img/profiles/profile_1_1765256297.png', 8, 'Langsung Final', 'Dec 31', 'Spearhead', 'Langsung Final', 'Registration', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'AdminGSC2026	', 'AdminGSC2026@setsystem.id', 'img/users/user_7_1765343374.png', '$2y$10$BlwOKXyv23EPrmmuOUx94OR7UFxeMylcVrZJMdvay8InOhKq2cFuG', 'Gadjah Mada Swimming Competition 2026', 'admin', '2025-12-09 02:47:53', '20 - 24 Agustus 2026', '2026-08-20', '2026-08-21', 'Kolam Renang Tirta Krida AAU ', 'Kab. Sleman, Provinsi D.I Yogyakarta', 'img/events/event_1765262738_6937c5923f9a8.png', 'img/profiles/profile_7_1765254439.png', 8, 'Penyisihan', 'Dec 31', 'Spearhead', 'Babak Penyisihan', 'Registration', 'BNI', '12345678910', 'Duhan Muhammad A', 'uploads/logos/logo_7_logo_left_1765614615.jpeg', 'uploads/logos/logo_7_logo_right_1765614700.png', 'uploads/logos/config_7_logo_sponsor_1765615814.png'),
(8, 'PariSaktiSCJogja', 'PariSaktiSCJogja@setsystem.id', 'img/users/user_8_1765373770.jpeg', '$2y$10$BQFoADj/40wpBeUC8wYdTuiwh7yRhJuZYXSF32f4zeWXIHFvtbxyy', 'Pari Sakti Swimming Club Yogyakarta', 'user', '2025-12-09 03:25:08', NULL, NULL, NULL, NULL, NULL, NULL, 'img/profiles/profile_8_1765256199.JPG', 8, 'Langsung Final', 'Dec 31', 'Spearhead', 'Langsung Final', 'Registration', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'UNYSwimTeam', 'UNYSwimTeam@setsystem.id', 'img/users/user_9_1765373942.png', '$2y$10$LH95t4r6zrEztVJ0WH.QO.zuwKqI4qLo/.qw.D1fJYa6MFuD9rKJ.', 'Universitas Negeri Yogyakarta Swimming Team', 'user', '2025-12-10 03:17:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, 'Langsung Final', 'Dec 31', 'Spearhead', 'Langsung Final', 'Registration', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'keputranswimmingschools638', 'kss@setsystem.id', 'img/users/user_10_1765550608.jpeg', '$2y$10$jLgZppkOcXUgY/B5dG54ZesapTkdGfirZTonrP0ubNeYmLa5ZBqKu', 'Keputran Swimming Schools ', 'user', '2025-12-12 03:13:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, 'Langsung Final', 'Dec 31', 'Spearhead', 'Langsung Final', 'Registration', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'UAJAYA@setsystem.id', 'UAJAYA@setsystem.id', 'img/users/user_11_1765851349.png', '$2y$10$TTpq98MLkU3X3vpfgJZuF.pU7v2Syl0ti6DNu4YLuvkNIa3057Muq', 'Universitas Atma Jaya', 'user', '2025-12-15 13:30:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, 'Langsung Final', 'Dec 31', 'Spearhead', 'Langsung Final', 'Registration', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'O2SNProvDIY2026@setsystem.id', 'O2SNProvDIY2026@setsystem.id', NULL, '$2y$10$oPbFcPfQ8/pbZ5YAnHtpF.N9A.2OMa/j30tdgBvbE2bB/EIxmcbpm', 'O2SN Tk Prov. DIY 2026', 'admin', '2025-12-15 13:31:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, 'Langsung Final', 'Dec 31', 'Spearhead', 'Langsung Final', 'Registration', NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `athlete_records`
--
ALTER TABLE `athlete_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_klub` (`kode_klub`),
  ADD KEY `fk_clubs_users` (`user_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `entries`
--
ALTER TABLE `entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_entry` (`event_id`,`swimmer_id`),
  ADD KEY `swimmer_id` (`swimmer_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_age_groups`
--
ALTER TABLE `event_age_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_entries`
--
ALTER TABLE `event_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `swimmer_id` (`swimmer_id`);

--
-- Indexes for table `event_payments`
--
ALTER TABLE `event_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_results`
--
ALTER TABLE `event_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_sponsors`
--
ALTER TABLE `event_sponsors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `heats`
--
ALTER TABLE `heats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `heat_entries`
--
ALTER TABLE `heat_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `heat_id` (`heat_id`,`lane_number`),
  ADD KEY `swimmer_id` (`swimmer_id`);

--
-- Indexes for table `hero_images`
--
ALTER TABLE `hero_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `meets`
--
ALTER TABLE `meets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `race_heats`
--
ALTER TABLE `race_heats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `race_lines`
--
ALTER TABLE `race_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `heat_id` (`heat_id`),
  ADD KEY `swimmer_id` (`swimmer_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meet_id` (`meet_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `swimmers`
--
ALTER TABLE `swimmers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `swimmer_events`
--
ALTER TABLE `swimmer_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `swimmer_id` (`swimmer_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `swimmer_records`
--
ALTER TABLE `swimmer_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `swimmer_id` (`swimmer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `athlete_records`
--
ALTER TABLE `athlete_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `entries`
--
ALTER TABLE `entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `event_age_groups`
--
ALTER TABLE `event_age_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `event_entries`
--
ALTER TABLE `event_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `event_payments`
--
ALTER TABLE `event_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_results`
--
ALTER TABLE `event_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_sponsors`
--
ALTER TABLE `event_sponsors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `heats`
--
ALTER TABLE `heats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `heat_entries`
--
ALTER TABLE `heat_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `hero_images`
--
ALTER TABLE `hero_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `meets`
--
ALTER TABLE `meets`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `race_heats`
--
ALTER TABLE `race_heats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `race_lines`
--
ALTER TABLE `race_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `swimmers`
--
ALTER TABLE `swimmers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `swimmer_events`
--
ALTER TABLE `swimmer_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `swimmer_records`
--
ALTER TABLE `swimmer_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clubs`
--
ALTER TABLE `clubs`
  ADD CONSTRAINT `fk_clubs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entries`
--
ALTER TABLE `entries`
  ADD CONSTRAINT `entries_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entries_ibfk_2` FOREIGN KEY (`swimmer_id`) REFERENCES `swimmers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_age_groups`
--
ALTER TABLE `event_age_groups`
  ADD CONSTRAINT `event_age_groups_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`swimmer_id`) REFERENCES `swimmers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `heats`
--
ALTER TABLE `heats`
  ADD CONSTRAINT `heats_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `heat_entries`
--
ALTER TABLE `heat_entries`
  ADD CONSTRAINT `heat_entries_ibfk_1` FOREIGN KEY (`heat_id`) REFERENCES `heats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `heat_entries_ibfk_2` FOREIGN KEY (`swimmer_id`) REFERENCES `swimmers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `race_heats`
--
ALTER TABLE `race_heats`
  ADD CONSTRAINT `race_heats_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `race_lines`
--
ALTER TABLE `race_lines`
  ADD CONSTRAINT `race_lines_ibfk_1` FOREIGN KEY (`heat_id`) REFERENCES `race_heats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `race_lines_ibfk_2` FOREIGN KEY (`swimmer_id`) REFERENCES `swimmers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`meet_id`) REFERENCES `meets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `swimmer_events`
--
ALTER TABLE `swimmer_events`
  ADD CONSTRAINT `swimmer_events_ibfk_1` FOREIGN KEY (`swimmer_id`) REFERENCES `swimmers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `swimmer_events_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `swimmer_records`
--
ALTER TABLE `swimmer_records`
  ADD CONSTRAINT `swimmer_records_ibfk_1` FOREIGN KEY (`swimmer_id`) REFERENCES `swimmers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
