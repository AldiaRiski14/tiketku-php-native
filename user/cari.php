<?php
$pageTitle = 'Cari Tiket';
require_once __DIR__ . '/../includes/header.php';

$jenis   = $_GET['jenis'] ?? '';
$asal    = trim($_GET['asal'] ?? '');
$tujuan  = trim($_GET['tujuan'] ?? '');
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$sql = "
    SELECT s.*, r.jenis, r.asal, r.tujuan, r.stasiun_bandara_asal, r.stasiun_bandara_tujuan
    FROM schedules s
    JOIN routes r ON s.route_id = r.id
    WHERE s.status IN ('scheduled','boarding')
      AND TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) > NOW()
";
$params = [];

if ($jenis) {
    $sql .= " AND r.jenis = ?";
    $params[] = $jenis;
}
if ($asal) {
    $sql .= " AND r.asal LIKE ?";
    $params[] = "%$asal%";
}
if ($tujuan) {
    $sql .= " AND r.tujuan LIKE ?";
    $params[] = "%$tujuan%";
}
if ($tanggal) {
    $sql .= " AND s.tanggal_berangkat = ?";
    $params[] = $tanggal;
}

$sql .= " ORDER BY s.tanggal_berangkat, s.jam_berangkat";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mb-6">Cari Tiket</h1>
    
    <!-- Filter Form -->
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Jenis</label>
                <select name="jenis" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                    <option value="">Semua</option>
                    <option value="kereta" <?= $jenis==='kereta'?'selected':'' ?>>Kereta Api</option>
                    <option value="pesawat" <?= $jenis==='pesawat'?'selected':'' ?>>Pesawat</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Asal</label>
                <input type="text" name="asal" value="<?= e($asal) ?>" placeholder="Jakarta..." class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tujuan</label>
                <input type="text" name="tujuan" value="<?= e($tujuan) ?>" placeholder="Bali..." class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tanggal</label>
                <input type="date" name="tanggal" value="<?= e($tanggal) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-xl transition">
                    Cari
                </button>
            </div>
        </div>
    </form>
    
    <!-- Results -->
    <div class="space-y-4">
        <?php if (empty($results)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="font-medium">Tidak ada jadwal ditemukan</p>
                <p class="text-sm mt-1">Coba ubah filter pencarian Anda</p>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-500 mb-2">Ditemukan <strong><?= count($results) ?></strong> jadwal</p>
            
            <?php foreach ($results as $s): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition flex flex-col md:flex-row md:items-center gap-5">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-primary-600"><?= transportIcon($s['jenis']) ?></span>
                        <span class="text-xs font-bold uppercase text-slate-500"><?= e($s['jenis']) ?></span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs font-medium text-slate-600"><?= e($s['kode_kendaraan']) ?> • <?= e($s['nama_kendaraan']) ?></span>
                        <?= statusBadge($s['status']) ?>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <div>
                            <div class="text-xl font-bold text-slate-800"><?= date('H:i', strtotime($s['jam_berangkat'])) ?></div>
                            <div class="text-sm font-medium text-slate-700"><?= e($s['asal']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($s['stasiun_bandara_asal']) ?></div>
                        </div>
                        <div class="flex-1 max-w-[120px] text-center">
                            <div class="text-xs text-slate-400 mb-1"><?= date('d M Y', strtotime($s['tanggal_berangkat'])) ?></div>
                            <div class="h-px bg-slate-300 relative">
                                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-primary-500"></div>
                                <div class="absolute right-0 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-primary-500"></div>
                            </div>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-slate-800"><?= $s['jam_tiba'] ? date('H:i', strtotime($s['jam_tiba'])) : '-' ?></div>
                            <div class="text-sm font-medium text-slate-700"><?= e($s['tujuan']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($s['stasiun_bandara_tujuan']) ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="md:text-right border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6 flex md:flex-col items-center md:items-end justify-between gap-3">
                    <div>
                        <div class="text-xs text-slate-400">Harga / org</div>
                        <div class="text-xl font-bold text-primary-700"><?= formatRupiah($s['harga_idr']) ?></div>
                        <div class="text-xs text-slate-400 mt-0.5"><?= $s['kursi_tersedia'] ?> kursi tersisa</div>
                    </div>
                    <?php if ($s['kursi_tersedia'] > 0): ?>
                    <a href="<?= APP_URL ?>/user/pesan.php?schedule_id=<?= $s['id'] ?>" 
                       class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm whitespace-nowrap">
                        Pesan Tiket
                    </a>
                    <?php else: ?>
                    <span class="text-sm text-red-500 font-medium">Habis</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
