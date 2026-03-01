# Rencana Pengembangan Portal Teknisi (Technician Dashboard)

Dokumen ini berisi rincian fitur dan rencana teknis untuk pembuatan portal khusus teknisi. Portal ini bertujuan untuk mempermudah manajemen tugas lapangan (Pasang Baru & Gangguan) serta manajemen perangkat pelanggan (ONT/WiFi).

## 1. Struktur & Akses
- **URL Akses**: `/technician/login.php`
- **Tabel Database**: `technician_users` (Baru)
  - `id`, `username`, `password`, `name`, `phone`, `status`, `created_at`
- **Dashboard**: Menampilkan ringkasan tugas hari ini (Pasang Baru & Tiket Gangguan).

## 2. Fitur Utama

### A. Manajemen Tugas (Task Management)
1.  **Tiket Gangguan (Trouble Tickets)**
    - Teknisi bisa melihat daftar tiket yang ditugaskan (Pending/In Progress).
    - Update status tiket: `Pending` -> `In Progress` -> `Resolved`.
    - Upload foto bukti perbaikan (opsional).
    - Menambahkan catatan penyelesaian (`resolution_notes`).

2.  **Pasang Baru (New Installation)**
    - Melihat daftar pelanggan baru yang statusnya `registered` (belum aktif).
    - Input data teknis instalasi:
        - Serial Number (SN) ONT.
        - Koordinat lokasi (ODP & Rumah Pelanggan).
        - Foto hasil instalasi.
    - Aktivasi pelanggan (ubah status jadi `active`).

### B. Manajemen Peta (Map Management)
1.  **Update Lokasi Pelanggan (Geotagging)**
    - Teknisi bisa mengupdate koordinat (Lat, Lng) pelanggan langsung di lapangan.
    - Menggunakan GPS HP untuk akurasi tinggi.
2.  **Manajemen ODP (Optical Distribution Point)**
    - Tambah data ODP baru (Nama, Koordinat, Kapasitas).
    - Update lokasi ODP yang sudah ada.
    - Melihat peta persebaran ODP dan Pelanggan di sekitar teknisi.

### C. Integrasi Perangkat (GenieACS / MikroTik)
1.  **Remote Config ONT (via GenieACS API)**
    - **Cari Pelanggan**: Cari berdasarkan Nama/ID Pelanggan.
    - **Status ONT**: Cek status online/offline, redaman (optical signal), dan uptime.
    - **Manajemen WiFi**:
        - Ganti Nama WiFi (SSID).
        - Ganti Password WiFi.
    - **Reboot ONT**: Restart modem jarak jauh.

2.  **Cek Koneksi (MikroTik)**
    - Cek status PPPoE user (Active/Offline).
    - Cek trafik/bandwidth usage sederhana.

## 3. Rencana Database (Schema Changes)

```sql
-- 1. Tabel User Teknisi
CREATE TABLE technician_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Update Tabel Trouble Tickets (Assignment)
ALTER TABLE trouble_tickets 
ADD COLUMN technician_id INT DEFAULT NULL,
ADD FOREIGN KEY (technician_id) REFERENCES technician_users(id) ON DELETE SET NULL;

-- 3. Update Tabel Customers (Instalasi)
ALTER TABLE customers
ADD COLUMN installed_by INT DEFAULT NULL, -- ID Teknisi
ADD COLUMN installation_date DATETIME DEFAULT NULL;
```

## 4. Struktur Folder (Proposed)
```
/technician
  ├── login.php           # Halaman Login
  ├── dashboard.php       # Home (Ringkasan Tugas)
  ├── tasks/
  │   ├── index.php       # Daftar Tugas (Tiket & PSB)
  │   ├── view_ticket.php # Detail Tiket & Aksi
  │   └── view_install.php# Detail PSB & Aktivasi
  ├── map/
  │   ├── index.php       # Peta Interaktif
  │   └── update_loc.php  # Update Koordinat (AJAX)
  ├── devices/
  │   ├── search.php      # Cari Pelanggan/ONT
  │   └── manage.php      # Ganti SSID/Pass, Cek Sinyal (GenieACS)
  ├── profile.php         # Ganti Password Teknisi
  └── logout.php
```

## 5.  **Integrasi Admin Panel**
- Menu baru di Admin: **Manajemen Teknisi**.
- Fitur Assign Tiket: Admin memilih teknisi saat membuat/mengedit tiket gangguan.
- Laporan Kinerja: Melihat berapa tiket/pasang baru yang diselesaikan per teknisi.

## 6. Update Installer & Produksi
- **Update `install.php`**:
  - Tambahkan skema tabel `technician_users` dalam proses instalasi baru.
  - Tambahkan kolom `technician_id` dan `photo_proof` di tabel `trouble_tickets`.
  - Tambahkan kolom `installed_by`, `installation_date`, dan `installation_photo` di tabel `customers`.
- **Update Script Produksi**: Buat file `update_db_technician.php` untuk memperbarui database di server yang sudah berjalan.

## 7. Langkah Pengerjaan (Phasing)
1.  **Fase 1: Setup & Auth**: Buat database, login page, dan dashboard dasar.
2.  **Fase 2: Manajemen Tiket**: Fitur ambil tiket, update status, dan penyelesaian.
3.  **Fase 3: Pasang Baru**: Fitur list pelanggan baru dan form aktivasi.
4.  **Fase 4: Integrasi GenieACS**: Fitur ganti SSID/Password dan cek sinyal.
5.  **Fase 5: Integrasi Admin**: Admin bisa assign tugas ke teknisi.

## 8. Requirement Tambahan (User Request)
- **Mobile Responsive (Wajib)**:
  - Tampilan harus optimal di layar HP (karena teknisi bekerja di lapangan).
  - Gunakan framework CSS (Bootstrap) dengan layout card/grid yang responsif.
  - Navigasi yang mudah diakses dengan jempol (bottom navigation atau burger menu besar).
- **Bukti Foto (Wajib)**:
  - Setiap penyelesaian tugas (Tiket/PSB) WAJIB upload foto bukti.
  - Kompresi gambar otomatis (agar tidak berat di server).
  - Preview foto sebelum submit.

---
**Catatan Penting:**
Fitur ganti SSID/Password memerlukan konfigurasi GenieACS yang sudah berjalan dan API yang dapat diakses dari server web ini.
