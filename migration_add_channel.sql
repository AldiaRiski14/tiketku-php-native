-- Jalankan ini di phpMyAdmin / MySQL jika database sudah ada sebelumnya
-- Menambah kolom purchase_channel (online / walkin)

ALTER TABLE tickets 
ADD COLUMN purchase_channel ENUM('online','walkin') DEFAULT 'online' 
AFTER payment_ref;
