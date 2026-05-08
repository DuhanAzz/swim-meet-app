-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 05, 2026 at 01:41 PM
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

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_klub` varchar(150) DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT 'Standard',
  `participation_type` varchar(50) DEFAULT 'club',
  `event_name` varchar(255) DEFAULT NULL,
  `event_location` varchar(255) DEFAULT NULL,
  `event_date_start` date DEFAULT NULL,
  `event_date_end` date DEFAULT NULL,
  `lane_count` int(11) DEFAULT 8,
  `competition_system` varchar(50) DEFAULT 'Langsung Final',
  `event_status` varchar(50) DEFAULT 'upcoming',
  `pool_type` varchar(10) DEFAULT '50m',
  `age_calculation_type` varchar(20) DEFAULT 'Dec 31',
  `logo_left` varchar(255) DEFAULT NULL,
  `logo_right` varchar(255) DEFAULT NULL,
  `sponsor_footer` varchar(255) DEFAULT NULL,
  `bank_name` varchar(50) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pricing_mode` enum('per_item','package') DEFAULT 'per_item',
  `package_price` decimal(15,2) DEFAULT 0.00,
  `package_limit` int(11) DEFAULT 0,
  `extra_price` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `event_entries`
--

CREATE TABLE `event_entries` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `swimmer_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entry_time` varchar(15) DEFAULT '00:00.00',
  `entry_time_ms` int(11) DEFAULT 999999999,
  `status` enum('Pending','Approved','Rejected','Scratched') DEFAULT 'Pending',
  `payment_status` enum('Unpaid','Paid') DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_numbers`
--

CREATE TABLE `event_numbers` (
  `id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `event_number` varchar(10) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `distance` int(11) NOT NULL,
  `stroke` varchar(50) NOT NULL,
  `jenis_kelamin` enum('L','P','Campuran') NOT NULL,
  `age_group` varchar(50) NOT NULL,
  `age_min` int(11) NOT NULL,
  `age_max` int(11) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `schedule_date` date DEFAULT NULL,
  `schedule_time` time DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `selected_ku_ids` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_seeding`
--

CREATE TABLE `event_seeding` (
  `id` int(11) NOT NULL,
  `entry_id` int(11) NOT NULL,
  `heat_prelim` int(3) DEFAULT NULL,
  `lane_prelim` int(3) DEFAULT NULL,
  `time_prelim` varchar(15) DEFAULT NULL,
  `time_prelim_ms` int(11) DEFAULT NULL,
  `rank_prelim` int(5) DEFAULT NULL,
  `is_dq_prelim` tinyint(1) DEFAULT 0,
  `dq_reason_prelim` varchar(255) DEFAULT NULL,
  `heat_final` int(3) DEFAULT NULL,
  `lane_final` int(3) DEFAULT NULL,
  `time_final` varchar(15) DEFAULT NULL,
  `time_final_ms` int(11) DEFAULT NULL,
  `rank_final` int(5) DEFAULT NULL,
  `is_dq_final` tinyint(1) DEFAULT 0,
  `dq_reason_final` varchar(255) DEFAULT NULL,
  `points` int(5) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_sponsors`
--

CREATE TABLE `event_sponsors` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `file_path` varchar(255) DEFAULT NULL COMMENT 'File Bukti Transfer',
  `admin_file_path` varchar(255) DEFAULT NULL COMMENT 'File Berkas Administrasi',
  `status` enum('Unpaid','Pending','Paid','Rejected') DEFAULT 'Unpaid',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `info_text` text DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT 'info@swimmeet.id',
  `contact_wa` varchar(20) DEFAULT '628123456789',
  `link_instagram` varchar(255) DEFAULT 'https://instagram.com',
  `link_facebook` varchar(255) DEFAULT '#',
  `site_description` text DEFAULT NULL,
  `app_name` varchar(50) DEFAULT 'SwimMeet App',
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `bank_name` varchar(50) DEFAULT 'BCA',
  `bank_account` varchar(50) DEFAULT '1234567890',
  `bank_holder` varchar(100) DEFAULT 'Yayasan Renang Indonesia',
  `allow_register` tinyint(1) DEFAULT 1,
  `show_announcement` tinyint(1) DEFAULT 0,
  `announcement_text` text DEFAULT NULL,
  `support_wa` varchar(50) DEFAULT NULL,
  `support_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `swimmers`
--

CREATE TABLE `swimmers` (
  `id` int(11) NOT NULL,
  `uid` varchar(12) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `nama_atlet` varchar(255) NOT NULL,
  `asal_sekolah` varchar(100) DEFAULT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL DEFAULT 'L',
  `tanggal_lahir` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','verified') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `swimmer_transfers`
--

CREATE TABLE `swimmer_transfers` (
  `id` int(11) NOT NULL,
  `swimmer_id` int(11) DEFAULT NULL,
  `old_club_id` int(11) DEFAULT NULL,
  `new_club_id` int(11) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('master','admin','user') DEFAULT 'user',
  `account_status` enum('pending','active','suspended') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `event_entries`
--
ALTER TABLE `event_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event` (`event_id`),
  ADD KEY `idx_club` (`club_id`),
  ADD KEY `idx_swimmer` (`swimmer_id`),
  ADD KEY `idx_number` (`category_id`),
  ADD KEY `idx_rank_prelim` (`category_id`),
  ADD KEY `idx_unique_check` (`category_id`,`swimmer_id`);

--
-- Indexes for table `event_numbers`
--
ALTER TABLE `event_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event_number` (`organizer_id`,`event_id`,`event_number`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `event_seeding`
--
ALTER TABLE `event_seeding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_entry_seeding` (`entry_id`);

--
-- Indexes for table `event_sponsors`
--
ALTER TABLE `event_sponsors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

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
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payment` (`user_id`,`event_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `swimmers`
--
ALTER TABLE `swimmers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid_2` (`uid`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `swimmer_transfers`
--
ALTER TABLE `swimmer_transfers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_age_groups`
--
ALTER TABLE `event_age_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_entries`
--
ALTER TABLE `event_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_numbers`
--
ALTER TABLE `event_numbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_seeding`
--
ALTER TABLE `event_seeding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_sponsors`
--
ALTER TABLE `event_sponsors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hero_images`
--
ALTER TABLE `hero_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `swimmers`
--
ALTER TABLE `swimmers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `swimmer_transfers`
--
ALTER TABLE `swimmer_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event_age_groups`
--
ALTER TABLE `event_age_groups`
  ADD CONSTRAINT `event_age_groups_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_seeding`
--
ALTER TABLE `event_seeding`
  ADD CONSTRAINT `fk_entry_seeding` FOREIGN KEY (`entry_id`) REFERENCES `event_entries` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
