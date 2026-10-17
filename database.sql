-- =====================================================
-- TIKETKU - Sistem Pemesanan Tiket Modern
-- Database Schema (MySQL / MariaDB)
-- =====================================================

CREATE DATABASE IF NOT EXISTS tiketku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tiketku;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20),
    alamat TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    account_status ENUM('active','banned','inactive') DEFAULT 'active',
    banned_until DATETIME DEFAULT NULL,
    ban_reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: currencies
-- -----------------------------------------------------
CREATE TABLE currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(5) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    rate_to_idr DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: routes (rute perjalanan)
-- -----------------------------------------------------
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis ENUM('kereta', 'pesawat') NOT NULL,
    kode_rute VARCHAR(20) NOT NULL,
    asal VARCHAR(100) NOT NULL,
    tujuan VARCHAR(100) NOT NULL,
    stasiun_bandara_asal VARCHAR(150),
    stasiun_bandara_tujuan VARCHAR(150),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: schedules (jadwal)
-- -----------------------------------------------------
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    kode_kendaraan VARCHAR(30) NOT NULL,          -- KA123 / GA-400
    nama_kendaraan VARCHAR(100),                 -- Argo Bromo / Garuda Indonesia
    tanggal_berangkat DATE NOT NULL,
    jam_berangkat TIME NOT NULL,
    tanggal_tiba DATE,
    jam_tiba TIME,
    harga_idr DECIMAL(15,2) NOT NULL,            -- harga dasar IDR
    total_kursi INT NOT NULL DEFAULT 50,
    kursi_tersedia INT NOT NULL DEFAULT 50,
    status ENUM('scheduled','boarding','departed','cancelled','completed') DEFAULT 'scheduled',
    boarding_start DATETIME,                     -- waktu mulai boarding
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: tickets
-- -----------------------------------------------------
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_tiket VARCHAR(30) NOT NULL UNIQUE,      -- TK-20260801-XXXX
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    jumlah_penumpang INT NOT NULL DEFAULT 1,
    total_harga_idr DECIMAL(15,2) NOT NULL,
    currency_code VARCHAR(5) DEFAULT 'IDR',
    total_harga_currency DECIMAL(15,2),
    status ENUM('pending','active','boarded','expired','cancelled','void') DEFAULT 'pending',
    metode_pembayaran ENUM('cash','qris','debit') DEFAULT NULL,
    payment_status ENUM('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
    payment_ref VARCHAR(100),
    purchase_channel ENUM('online','walkin') DEFAULT 'online', -- online=website, walkin=loket
    barcode_data VARCHAR(100) NOT NULL,          -- data untuk QR/Barcode
    boarded_at DATETIME DEFAULT NULL,
    boarded_by INT DEFAULT NULL,                 -- admin yang scan
    warning_sent TINYINT(1) DEFAULT 0,           -- sudah dikirim peringatan in-app
    email_ticket_sent TINYINT(1) DEFAULT 0,      -- tiket sudah dikirim via email
    email_warning_sent TINYINT(1) DEFAULT 0,     -- peringatan keberangkatan sudah dikirim email
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: passengers (detail penumpang per tiket)
-- -----------------------------------------------------
CREATE TABLE passengers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    nama_penumpang VARCHAR(100) NOT NULL,
    no_identitas VARCHAR(30),                    -- KTP / Passport
    tipe_identitas ENUM('ktp','passport','sim') DEFAULT 'ktp',
    kursi VARCHAR(10),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: notifications
-- -----------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_id INT DEFAULT NULL,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    tipe ENUM('info','warning','danger','success') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: email_logs (tracking email yang dikirim)
-- -----------------------------------------------------
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    email_to VARCHAR(150) NOT NULL,
    email_type ENUM('ticket','warning','boarding','void','custom') NOT NULL,
    subject VARCHAR(200),
    status ENUM('pending','sent','failed') DEFAULT 'pending',
    response_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: system_settings
-- -----------------------------------------------------
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA
-- =====================================================

-- Admin default (password: admin123)
INSERT INTO users (nama_lengkap, email, password, no_hp, role) VALUES
('Administrator', 'admin@tiketku.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567890', 'admin');

-- User demo (password: user123)
INSERT INTO users (nama_lengkap, email, password, no_hp, role) VALUES
('Budi Santoso', 'budi@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081298765432', 'user'),
('Siti Aminah', 'siti@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081211122233', 'user');

-- Currencies
INSERT INTO currencies (code, name, symbol, rate_to_idr) VALUES
('IDR', 'Indonesian Rupiah', 'Rp', 1.000000),
('USD', 'US Dollar', '$', 16250.000000),
('EUR', 'Euro', '€', 17600.000000),
('SGD', 'Singapore Dollar', 'S$', 12100.000000),
('MYR', 'Malaysian Ringgit', 'RM', 3450.000000),
('JPY', 'Japanese Yen', '¥', 105.000000);

-- Routes
INSERT INTO routes (jenis, kode_rute, asal, tujuan, stasiun_bandara_asal, stasiun_bandara_tujuan) VALUES
('kereta', 'KA-JKT-BDG', 'Jakarta', 'Bandung', 'Stasiun Gambir', 'Stasiun Bandung'),
('kereta', 'KA-JKT-SBY', 'Jakarta', 'Surabaya', 'Stasiun Gambir', 'Stasiun Surabaya Gubeng'),
('kereta', 'KA-BDG-YGY', 'Bandung', 'Yogyakarta', 'Stasiun Bandung', 'Stasiun Tugu'),
('pesawat', 'GA-JKT-DPS', 'Jakarta', 'Denpasar', 'Soekarno-Hatta (CGK)', 'Ngurah Rai (DPS)'),
('pesawat', 'JT-JKT-SUB', 'Jakarta', 'Surabaya', 'Soekarno-Hatta (CGK)', 'Juanda (SUB)'),
('pesawat', 'QZ-JKT-KUL', 'Jakarta', 'Kuala Lumpur', 'Soekarno-Hatta (CGK)', 'KLIA (KUL)');

-- Schedules (contoh data hari ini + beberapa hari ke depan)
INSERT INTO schedules (route_id, kode_kendaraan, nama_kendaraan, tanggal_berangkat, jam_berangkat, tanggal_tiba, jam_tiba, harga_idr, total_kursi, kursi_tersedia, status, boarding_start) VALUES
(1, 'KA-120', 'Argo Parahyangan', CURDATE(), '08:00:00', CURDATE(), '11:30:00', 150000, 40, 35, 'scheduled', CONCAT(CURDATE(), ' 07:30:00')),
(1, 'KA-122', 'Argo Parahyangan', CURDATE(), '14:00:00', CURDATE(), '17:30:00', 160000, 40, 40, 'scheduled', CONCAT(CURDATE(), ' 13:30:00')),
(2, 'KA-45', 'Argo Bromo Anggrek', CURDATE(), '09:00:00', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '06:00:00', 450000, 50, 42, 'scheduled', CONCAT(CURDATE(), ' 08:30:00')),
(4, 'GA-400', 'Garuda Indonesia', CURDATE(), '10:30:00', CURDATE(), '13:15:00', 1250000, 180, 150, 'scheduled', CONCAT(CURDATE(), ' 09:30:00')),
(4, 'GA-412', 'Garuda Indonesia', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '07:00:00', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:45:00', 1180000, 180, 180, 'scheduled', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 06:00:00')),
(5, 'JT-680', 'Lion Air', CURDATE(), '16:00:00', CURDATE(), '17:45:00', 780000, 180, 160, 'scheduled', CONCAT(CURDATE(), ' 15:00:00')),
(6, 'QZ-250', 'AirAsia', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', 950000, 180, 175, 'scheduled', CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 10:00:00')),
(3, 'KA-78', 'Lodaya', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '06:30:00', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00:00', 280000, 45, 45, 'scheduled', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 06:00:00'));

-- Settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('app_name', 'TiketKu'),
('app_tagline', 'Pesan Tiket Kereta & Pesawat dengan Mudah'),
('warning_minutes_before', '10'),          -- peringatan 10 menit sebelum berangkat (sesuai permintaan)
('void_after_departed', '1'),              -- hangus jika sudah berangkat
('support_email', 'support@tiketku.com'),
('support_phone', '1500-123'),
('emailjs_public_key', 'YOUR_EMAILJS_PUBLIC_KEY'),   -- ganti dengan public key EmailJS Anda
('emailjs_service_id', 'YOUR_SERVICE_ID'),           -- ganti dengan Service ID
('emailjs_template_ticket', 'YOUR_TEMPLATE_TICKET'), -- template untuk kirim tiket
('emailjs_template_warning', 'YOUR_TEMPLATE_WARNING'); -- template peringatan keberangkatan
