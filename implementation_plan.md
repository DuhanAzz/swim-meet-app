# Dokumen Implementasi Teknis: Centralized Record Package System

Berdasarkan *Business Requirements* terbaru, arsitektur Rekor Event dirombak menjadi model **Centralized Package System**. Model ini memisahkan beban kerja berat (agregasi & kalkulasi) murni ke sisi Master Admin, sehingga Admin Event biasa cukup menggunakan (konsumsi) data yang sudah jadi.

---

## 1. DATABASE SCHEMA SCRIPT (SQL)

Berikut adalah query SQL lengkap yang siap dieksekusi untuk membangun fondasi arsitektur baru ini. Harap diperhatikan bahwa penamaan tabel dan relasi `FOREIGN KEY` telah dirancang untuk menghindari anomali *Orphan Data*.

```sql
-- 1. Buat Tabel Induk (Paket Rekor)
CREATE TABLE `record_packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Buat Tabel Anak (Detail Rekor Event per Paket)
CREATE TABLE `event_historical_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `source_event_id` INT DEFAULT NULL,
    `distance` INT NOT NULL,
    `stroke` VARCHAR(50) NOT NULL,
    `jenis_kelamin` ENUM('L','P') NOT NULL,
    `age_group` VARCHAR(50) NOT NULL,
    `holder_name` VARCHAR(150) NOT NULL,
    `record_time` VARCHAR(20) NOT NULL,
    `record_time_ms` BIGINT NOT NULL,
    CONSTRAINT `fk_record_package` FOREIGN KEY (`package_id`) 
        REFERENCES `record_packages`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_source_event` FOREIGN KEY (`source_event_id`) 
        REFERENCES `events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Modifikasi Tabel `events` (Tambahkan relasi ke Paket Rekor)
ALTER TABLE `events`
ADD COLUMN `record_package_id` INT NULL AFTER `event_status`,
ADD CONSTRAINT `fk_events_record_package` FOREIGN KEY (`record_package_id`) 
    REFERENCES `record_packages`(`id`) ON DELETE SET NULL;
```

---

## 2. FILE STRUCTURE & WORKFLOW

### A. Sisi Master Admin (Pusat Kendali Paket)
Folder baru: `src/master/record_packages/`

1. **`index.php`** (Daftar Paket)
   - *Fungsi:* Menampilkan tabel daftar Paket Rekor yang pernah dibuat (misal: "Grup Rekor O2SN", "Grup Rekor Jatim Open"). Dilengkapi tombol "Buat Paket Baru" dan "Lihat/Edit Detail".
2. **`create.php`** (Form Pembuatan & Engine Pencari)
   - *Fungsi:* Master mengetik nama paket, mencari event-event historis (via input search atau multi-select), lalu menekan tombol kalkulasi.
3. **`process_aggregate.php`** (Logika *Backend* Tak Terlihat)
   - *Fungsi:* Menerima *array* ID event historis. Melakukan agregasi mencari waktu tercepat absolut per nomor acara (`MIN(time_final_ms)`). Hasilnya lalu disimpan (INSERT) ke tabel `event_historical_records`.
4. **`detail.php`** (Viewer Hasil)
   - *Fungsi:* Menampilkan daftar lengkap perenang yang mencetak rekor di dalam suatu paket yang sudah selesai diagregasi.

### B. Sisi Event Admin (Pengguna Paket)
Modifikasi akan dilakukan pada file-file pengaturan dan percetakan dokumen eksisting.

1. **`src/admin/settings/event_profile.php`**
   - *Modifikasi:* Menambahkan dropdown `<select name="record_package_id">` di form pengaturan. Dropdown ini menarik seluruh baris dari tabel `record_packages`. Admin cukup memilih, lalu klik Simpan (yang akan memicu operasi `UPDATE events SET record_package_id = ...`).
2. **`src/admin/seeding/print_full_book.php` & `export_all_results.php`**
   - *Modifikasi:* Saat mencetak buku acara, sistem akan memeriksa apakah event ini memiliki `record_package_id`. Jika ya, selain mengambil Rekornas dari `master_records`, sistem juga akan menarik rekor khusus kejuaraan (`event_historical_records`) berdasarkan ID paket tersebut.
3. **`src/admin/results/input_result.php` & Live Result**
   - *Modifikasi:* Menyesuaikan notifikasi "Pecah Rekor!". Logikanya diubah agar membandingkan waktu perenang bukan dengan teks bebas dari tabel master, melainkan dari data baku yang ada di `event_historical_records` paket yang dipilih.

---

## User Review Required

> [!IMPORTANT]
> Script SQL akan langsung diterapkan ke database lokal (XAMPP) Anda setelah rencana ini disetujui. Apakah struktur tabel dan rencana modifikasi UI ini sudah sepenuhnya sesuai dengan visi Anda?
