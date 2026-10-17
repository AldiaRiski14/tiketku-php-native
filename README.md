# TiketKu v2 — Sistem Pemesanan Tiket Kereta & Pesawat (Lengkap + EmailJS)

Sistem pemesanan tiket modern berbasis **PHP Native** dengan desain profesional, multi-currency, 3 metode pembayaran, barcode boarding, sistem anti-hangus, **dan pengiriman tiket + peringatan otomatis via EmailJS**.

## Fitur Utama (tanpa tumpang tindih)

### User
- Registrasi & Login
- Cari & pesan tiket Kereta / Pesawat (multi penumpang)
- Multi-currency: IDR, USD, EUR, SGD, MYR, JPY
- Pembayaran: Cash / QRIS / Debit (simulasi)
- Setelah bayar → status **ACTIVE** + **otomatis kirim tiket digital ke email user** (via EmailJS)
- QR Code di halaman detail tiket
- Notifikasi in-app + **email peringatan ±10 menit sebelum keberangkatan**
- Riwayat tiket lengkap

### Admin
- Dashboard statistik
- **Scan Boarding** (ubah status jadi BOARDED)
- Kelola status jadwal (saat Departed → tiket ACTIVE yang belum scan otomatis VOID)
- Kirim peringatan manual
- Log email terkirim

### Sistem Otomatis (bersih, tidak overlap)
1. Bayar sukses → tiket ACTIVE + email tiket dikirim (EmailJS)
2. ±10 menit sebelum berangkat & belum boarding → notifikasi in-app + email peringatan (EmailJS)
3. Kendaraan Departed / waktu lewat & masih ACTIVE → status VOID + notifikasi hangus
4. Satu peringatan per tiket (dicegah duplikat)

---

## Instalasi

### 1. Requirement
- PHP 7.4+ / 8.x
- MySQL / MariaDB
- PDO, pdo_mysql

### 2. Import Database
```bash
mysql -u root -p < database.sql
```
Atau via phpMyAdmin. **Penting:** pakai database.sql terbaru (ada kolom email_ticket_sent, email_warning_sent, tabel email_logs).

### 3. Konfigurasi
Edit `config/database.php`:
- DB credentials
- `APP_URL`
- **EmailJS keys** (penting!)

### 4. Setup EmailJS (wajib untuk fitur email)

1. Daftar gratis di https://www.emailjs.com
2. **Email Services** → Connect email Anda (Gmail / Outlook) → ini jadi email pengirim default ("email saya")
3. Buat **2 Email Template**:

**Template 1 – Tiket (setelah bayar)**  
Pakai variabel:
```
{{to_email}} {{to_name}} {{subject}}
{{kode_tiket}} {{nama_user}} {{rute}} {{kendaraan}}
{{tanggal}} {{jam_berangkat}} {{stasiun_asal}} {{stasiun_tujuan}}
{{penumpang}} {{jumlah}} {{total}} {{metode}} {{barcode}}
{{pesan_penting}}
```

**Template 2 – Peringatan Keberangkatan**  
```
{{to_email}} {{to_name}} {{subject}}
{{kode_tiket}} {{nama_user}} {{rute}} {{kendaraan}}
{{menit_tersisa}} {{pesan}}
```

4. Copy Public Key, Service ID, Template ID
5. Paste ke `config/database.php`

---

## Akun Demo
| Role  | Email             | Password |
|-------|-------------------|----------|
| Admin | admin@tiketku.com | admin123 |
| User  | budi@email.com    | user123  |

---

## Alur Lengkap

1. User pesan → isi data → pilih metode bayar
2. Konfirmasi bayar → status ACTIVE
3. Redirect ke halaman **sukses** → EmailJS otomatis kirim tiket ke email user
4. Menjelang berangkat (±10 menit) → sistem deteksi → notifikasi in-app + email peringatan
5. Admin scan barcode → status BOARDED
6. Jika sudah berangkat & belum scan → otomatis VOID (hangus)

---

## Struktur Folder
```
tiketku/
├── admin/          → Dashboard, Scan, Jadwal, Tiket
├── user/           → Login, Cari, Pesan, Bayar, Sukses, Detail, Notifikasi
├── process/        → logout, mark_email
├── config/         → database.php (termasuk EmailJS)
├── includes/       → header, footer, functions
├── database.sql
└── README.md
```

---

## Catatan

- EmailJS client-side: email tiket dikirim saat user membuka halaman sukses setelah bayar.
- Email peringatan dikirim saat user membuka detail tiket mendekati waktu berangkat.
- Tidak ada fitur tumpang tindih. Setiap status & notifikasi punya tanggung jawab jelas.
- Untuk skala besar dengan email benar-benar background, bisa ditambah PHPMailer + cron nanti.

Versi 2.0.0 — PHP Native + Tailwind + EmailJS
