<?php
$pageTitle = 'Beranda';
require_once __DIR__ . '/includes/header.php';

// Ambil beberapa jadwal terdekat
$stmt = $pdo->query("
    SELECT s.*, r.jenis, r.asal, r.tujuan, r.stasiun_bandara_asal, r.stasiun_bandara_tujuan
    FROM schedules s
    JOIN routes r ON s.route_id = r.id
    WHERE s.status IN ('scheduled','boarding')
      AND TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) > NOW()
    ORDER BY s.tanggal_berangkat, s.jam_berangkat
    LIMIT 6
");
$upcoming = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="gradient-hero text-white relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-10 left-10 w-72 h-72 bg-white rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-white rounded-full blur-3xl"></div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24 relative">
        <div class="text-center max-w-3xl mx-auto">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight mb-6 leading-tight">
                Pesan Tiket <span class="text-amber-300">Kereta</span> & <span class="text-amber-300">Pesawat</span><br>
                Lebih Mudah & Aman
            </h1>
            <p class="text-lg md:text-xl text-blue-100 mb-10 leading-relaxed">
                Multi-currency • QRIS • Debit • Cash<br>
                Notifikasi real-time & proteksi tiket hangus
            </p>
            
            <!-- Search Box -->
            <form action="<?= APP_URL ?>/user/cari.php" method="GET" class="bg-white rounded-2xl shadow-2xl p-4 md:p-6 text-left max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Jenis</label>
                        <select name="jenis" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
                            <option value="">Semua</option>
                            <option value="kereta">Kereta Api</option>
                            <option value="pesawat">Pesawat</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Asal</label>
                        <input type="text" name="asal" placeholder="Jakarta, Bandung..." class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Tujuan</label>
                        <input type="text" name="tujuan" placeholder="Surabaya, Bali..." class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>
                <button type="submit" class="mt-4 w-full md:w-auto bg-primary-600 hover:bg-primary-700 text-white font-bold py-3.5 px-8 rounded-xl shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Cari Tiket Sekarang
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Features -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-lg border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Multi Pembayaran</h3>
                <p class="text-xs text-slate-500">Cash • QRIS • Debit</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-lg border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-green-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Multi Currency</h3>
                <p class="text-xs text-slate-500">IDR • USD • EUR • SGD</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-lg border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Anti Hangus</h3>
                <p class="text-xs text-slate-500">Peringatan otomatis</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-lg border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Scan Barcode</h3>
                <p class="text-xs text-slate-500">Konfirmasi boarding</p>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Schedules -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold text-slate-800">Jadwal Terdekat</h2>
            <p class="text-slate-500 mt-1">Pilih perjalanan favoritmu</p>
        </div>
        <a href="<?= APP_URL ?>/user/cari.php" class="text-primary-600 font-semibold text-sm hover:underline">Lihat Semua →</a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 stagger">
        <?php foreach ($upcoming as $s): ?>
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden card-hover shadow-sm">
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-primary-600"><?= transportIcon($s['jenis']) ?></span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e($s['jenis']) ?></span>
                    </div>
                    <?= statusBadge($s['status']) ?>
                </div>
                
                <div class="flex items-center justify-between mb-4">
                    <div class="text-center">
                        <div class="text-lg font-bold text-slate-800"><?= e($s['asal']) ?></div>
                        <div class="text-xs text-slate-400"><?= e($s['stasiun_bandara_asal']) ?></div>
                    </div>
                    <div class="flex-1 px-3 flex flex-col items-center">
                        <div class="text-xs text-slate-400 mb-1"><?= date('H:i', strtotime($s['jam_berangkat'])) ?></div>
                        <div class="w-full h-px bg-slate-200 relative">
                            <div class="absolute left-1/2 -translate-x-1/2 -top-1.5 w-3 h-3 rounded-full bg-primary-500 border-2 border-white"></div>
                        </div>
                        <div class="text-xs text-slate-400 mt-1"><?= $s['jam_tiba'] ? date('H:i', strtotime($s['jam_tiba'])) : '-' ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-slate-800"><?= e($s['tujuan']) ?></div>
                        <div class="text-xs text-slate-400"><?= e($s['stasiun_bandara_tujuan']) ?></div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between text-sm text-slate-600 mb-4">
                    <span><?= e($s['kode_kendaraan']) ?> • <?= e($s['nama_kendaraan']) ?></span>
                    <span><?= date('d M Y', strtotime($s['tanggal_berangkat'])) ?></span>
                </div>
                
                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <div>
                        <div class="text-xs text-slate-400">Mulai dari</div>
                        <div class="text-lg font-bold text-primary-700"><?= formatRupiah($s['harga_idr']) ?></div>
                    </div>
                    <a href="<?= APP_URL ?>/user/pesan.php?schedule_id=<?= $s['id'] ?>" 
                       class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-md">
                        Pesan
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($upcoming)): ?>
        <div class="col-span-full text-center py-12 text-slate-500">
            Belum ada jadwal tersedia saat ini.
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- How it works -->
<section class="bg-slate-100 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-slate-800">Cara Kerja Sistem</h2>
            <p class="text-slate-500 mt-2">Sederhana, aman, dan transparan</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="w-14 h-14 bg-primary-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-4 shadow-lg">1</div>
                <h3 class="font-semibold text-slate-800 mb-2">Cari & Pesan</h3>
                <p class="text-sm text-slate-500">Pilih rute, tanggal, dan jumlah penumpang. Isi data dengan lengkap.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-primary-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-4 shadow-lg">2</div>
                <h3 class="font-semibold text-slate-800 mb-2">Bayar</h3>
                <p class="text-sm text-slate-500">Cash, QRIS, atau Debit. Support multi-currency (USD, EUR, dll).</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-primary-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-4 shadow-lg">3</div>
                <h3 class="font-semibold text-slate-800 mb-2">Dapatkan Tiket</h3>
                <p class="text-sm text-slate-500">Tiket aktif + QR Code. Siap digunakan untuk boarding.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-primary-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-4 shadow-lg">4</div>
                <h3 class="font-semibold text-slate-800 mb-2">Scan Boarding</h3>
                <p class="text-sm text-slate-500">Admin scan barcode. Status jadi <b>Boarded</b>. Tiket aman!</p>
            </div>
        </div>
    </div>
</section>

<!-- Warning Info -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-6 md:p-8 flex flex-col md:flex-row items-start gap-5">
        <div class="w-12 h-12 bg-amber-500 text-white rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
            <h3 class="font-bold text-amber-900 text-lg mb-2">Penting: Aturan Boarding & Tiket Hangus</h3>
            <ul class="text-sm text-amber-800 space-y-1.5 list-disc list-inside">
                <li>Setelah pembayaran berhasil, status tiket menjadi <strong>ACTIVE</strong>.</li>
                <li>Wajib <strong>scan barcode</strong> saat naik kendaraan agar status berubah menjadi <strong>BOARDED</strong>.</li>
                <li>Sistem akan mengirim <strong>peringatan otomatis</strong> 30 menit sebelum keberangkatan jika Anda belum boarding.</li>
                <li>Jika kendaraan sudah berangkat dan Anda belum scan → tiket otomatis <strong>HANGUS (VOID)</strong>.</li>
                <li>Pastikan tiba di lokasi boarding minimal 45 menit sebelumnya.</li>
            </ul>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
