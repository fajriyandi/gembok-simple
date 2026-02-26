## Task Web Sales & Sistem Deposit

Dokumen ini merangkum rencana pengembangan **Web Sales** terpisah dengan sistem **deposit** untuk penjualan voucher / pembayaran oleh sales.

---

### T1 – Desain Database Sales & Deposit

- Buat tabel `sales_users`:
  - `id` (INT, AUTO_INCREMENT, PK)
  - `name` (VARCHAR)
  - `phone` (VARCHAR)
  - `username` (VARCHAR, unique)
  - `password` (VARCHAR, hash `password_hash`)
  - `deposit_balance` (DECIMAL(15,2), default 0)
  - `status` ENUM(`active`,`inactive`) atau TINYINT
  - `created_at`, `updated_at` (DATETIME)
- Buat tabel `sales_transactions`:
  - `id` (INT, AUTO_INCREMENT, PK)
  - `sales_user_id` (FK ke `sales_users.id`)
  - `type` ENUM(`deposit`,`voucher_sale`,`adjustment`)
  - `amount` (DECIMAL(15,2)) → positif = tambah saldo, negatif = potong saldo
  - `description` (TEXT/VARCHAR)
  - `related_username` (username voucher jika `voucher_sale`)
  - `created_at` (DATETIME)
- Buat tabel `sales_profile_prices` (mapping profile per sales):
  - `id` (INT, AUTO_INCREMENT, PK)
  - `sales_user_id` (FK ke `sales_users.id`)
  - `profile_name` (VARCHAR, nama profile hotspot di MikroTik)
  - `base_price` (DECIMAL(15,2), harga modal dari sisi owner)
  - `selling_price` (DECIMAL(15,2), harga jual ke pelanggan)
  - `voucher_length` (INT, jumlah karakter username/password voucher untuk profile ini)
  - `is_active` (TINYINT(1), apakah profile ini boleh dipakai sales tsb)
  - `created_at`, `updated_at` (DATETIME)
- Tambah kolom `sales_user_id` pada tabel `hotspot_sales`:
  - INT (nullable di awal), FK ke `sales_users.id`

---

### T2 – Autentikasi & Role Sales

- Tambah fungsi di `includes/auth.php`:
  - `salesLogin($username, $password)`
  - `isSalesLoggedIn()`
  - `requireSalesLogin()`
- Gunakan session khusus:
  - `$_SESSION['sales'] = ['id', 'name', 'username', 'logged_in', 'login_time']`
- Pastikan pemisahan:
  - Admin: `$_SESSION['admin']`
  - Customer: `$_SESSION['customer']`
  - Sales: `$_SESSION['sales']`
- Buat halaman login sales:
  - `sales/login.php`
  - Form username + password
  - Redirect ke `sales/dashboard.php` jika sukses

---

### T3 – Portal Sales: Dashboard Dasar

- Buat folder `sales/`:
  - `sales/dashboard.php`
- Isi dashboard:
  - Tampilkan saldo deposit (`deposit_balance` dari `sales_users`)
  - Ringkasan penjualan (hanya milik sales yang login):
    - Total voucher terjual hari ini
    - Total voucher terjual bulan ini
    - Total modal yang dipakai (dari `price`)
    - Total omzet / harga jual (dari `selling_price`)
  - Link/menu:
    - "Buat Voucher"
    - "Riwayat Penjualan"
    - "Riwayat Deposit"
  - Desain layout **mobile first**:
    - Gunakan grid/flex yang rapih di layar HP
    - Pastikan tombol besar dan mudah di-tap di mobile

---

### T4 – Modul Transaksi Voucher oleh Sales

- Buat halaman `sales/vouchers.php`:
  - Wajib `requireSalesLogin()`
  - Form:
    - Pilih profile (LIST hanya profile yang di‑aktifkan untuk sales tersebut,
      berdasarkan tabel `sales_profile_prices` dengan `is_active = 1`)
    - Qty (default 1)
    - Prefix (opsional, contoh: `S1-`)
  - Data harga:
    - Saat transaksi, **tidak perlu input harga manual oleh sales**
    - Sistem ambil `base_price` dan `selling_price` dari tabel `sales_profile_prices`
      untuk kombinasi (sales_user_id, profile_name)
- Flow proses:
  1. Hitung total modal: `total_modal = price * qty`
  2. Cek saldo deposit sales (`deposit_balance`):
     - Jika `deposit_balance < total_modal` → tolak transaksi, tampilkan pesan "Saldo tidak mencukupi"
  3. Jika cukup:
     - Loop `qty` kali:
       - Generate voucher (mirip `admin/hotspot-user.php`):
         - Panggil `mikrotikAddHotspotUser`
         - Panggil `recordHotspotSale()` dan tambahkan parameter `sales_user_id`
       - Simpan voucher ke array untuk ditampilkan / cetak
     - Kurangi deposit:
       - Update `sales_users.deposit_balance -= total_modal`
       - Insert ke `sales_transactions`:
         - `type = 'voucher_sale'`
         - `amount = -total_modal`
         - `description` berisi informasi profil dan qty
  4. Tampilkan daftar voucher yang berhasil dibuat (username, password, profile, harga).

---

### T5 – Manajemen Deposit oleh Admin

- Buat halaman admin baru, misalnya `admin/sales-users.php`:
  - CRUD data sales:
    - Tambah sales baru (nama, username, password, saldo awal)
    - Edit data sales (nama, phone, status)
    - Nonaktifkan sales
  - Fitur topup deposit:
    - Form input nominal topup
    - Aksi:
      - Update `sales_users.deposit_balance += nominal`
      - Insert ke `sales_transactions`:
        - `type = 'deposit'`
        - `amount = nominal`
        - `description = 'Topup oleh admin ...'`
- Optional:
  - Fitur koreksi saldo (type `adjustment`).

---

### T6 – Riwayat & Laporan Sales

- Halaman `sales/history.php`:
  - Filter tanggal (dari–sampai)
  - Tabel penjualan voucher:
    - Data dari `hotspot_sales` dengan filter `sales_user_id = sales yang login`
  - Tabel mutasi deposit:
    - Data dari `sales_transactions` dengan filter `sales_user_id`
- Tambah laporan di admin (opsional):
  - Laporan penjualan per sales (periode harian / bulanan)
  - Ranking sales berdasarkan omzet / jumlah voucher

---

### T7 – Keamanan & Pembatasan Akses

- Pastikan:
  - Semua file di `/sales/` menggunakan `requireSalesLogin()` (kecuali `login.php`).
  - Sales **tidak bisa** mengakses halaman admin (`/admin/*`).
  - Query data selalu menggunakan `WHERE sales_user_id = :id` untuk halaman sales.
- Sanitasi input:
  - Gunakan `sanitize()` untuk semua input form.
- Pertimbangan tambahan:
  - Batasi qty maksimal per transaksi untuk mencegah salah input besar.
  - (Opsional) Tambah token CSRF untuk form penting (topup, generate voucher).

---

### T8 – Responsif & Mobile Friendly

- Portal sales akan sering diakses dari HP:
  - Semua halaman `sales/*` harus responsif di layar kecil (≤ 414px)
  - Gunakan layout sederhana: 1 kolom di mobile, 2–3 kolom di desktop
  - Form dan tombol diberi padding cukup untuk jari
  - Pastikan tabel riwayat dapat di-scroll horizontal di mobile

---

### T8 – Integrasi dengan Dashboard Admin

- Tambah ringkasan ke dashboard admin:
  - Total penjualan hotspot (semua sales) hari ini/bulan ini.
  - Total keuntungan (selisih selling_price - price).
- Tambah menu di sidebar admin:
  - "Sales Users" → manajemen sales & topup deposit.
  - "Sales Report" → laporan penjualan per sales (bisa reuse `hotspot_sales` + `sales_users`).

---

### T9 – Notifikasi Voucher ke Sales & Pelanggan (Opsional)

- Tambah opsi notifikasi di pengaturan (nanti bisa dihubungkan ke WhatsApp API / Telegram / email):
  - Notif ke sales:
    - Kirim detail voucher yang baru dibuat (username, password, profile, masa aktif)
  - Notif ke pelanggan (opsional):
    - Sales input nomor pelanggan (WA / Telegram / email)
    - Sistem kirim voucher langsung ke pelanggan
- Setiap sales bisa diatur:
  - Apakah notifikasi ke dirinya sendiri aktif/nonaktif
  - Channel utama (misalnya lebih prioritas WhatsApp daripada email)

