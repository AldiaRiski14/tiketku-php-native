<?php
$pageTitle = 'Manajemen Tiket';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Kirim peringatan manual
if (isset($_POST['send_warning']) && isset($_POST['ticket_id'])) {
    $tid = (int)$_POST['ticket_id'];
    $stmt = $pdo->prepare("SELECT t.*, u.id as uid, s.kode_kendaraan FROM tickets t JOIN users u ON t.user_id = u.id JOIN schedules s ON t.schedule_id = s.id WHERE t.id = ?");
    $stmt->execute([$tid]);
    $t = $stmt->fetch();
    if ($t && $t['status'] === 'active') {
        createNotification($t['uid'], 'Peringatan dari Admin', "Admin mengingatkan: Segera boarding untuk tiket {$t['kode_tiket']} ({$t['kode_kendaraan']}) sebelum keberangkatan!", 'warning', $tid);
        $pdo->prepare("UPDATE tickets SET warning_sent = 1 WHERE id = ?")->execute([$tid]);
        setFlash('success', 'Peringatan berhasil dikirim.');
    }
    redirect(APP_URL . '/admin/tiket.php');
}

$statusFilter = $_GET['status'] ?? '';
$sql = "
    SELECT t.*, s.kode_kendaraan, s.tanggal_berangkat, s.jam_berangkat, u.nama_lengkap, u.no_hp, r.asal, r.tujuan, r.jenis
    FROM tickets t
    JOIN schedules s ON t.schedule_id = s.id
    JOIN users u ON t.user_id = u.id
    JOIN routes r ON s.route_id = r.id
";
$params = [];
if ($statusFilter) {
    $sql .= " WHERE t.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY t.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Manajemen Tiket</h1>
        <div class="flex gap-2">
            <a href="?status=" class="text-sm px-3 py-1.5 rounded-lg <?= !$statusFilter ? 'bg-primary-100 text-primary-800' : 'bg-slate-100 text-slate-600' ?>">Semua</a>
            <a href="?status=active" class="text-sm px-3 py-1.5 rounded-lg <?= $statusFilter==='active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">Active</a>
            <a href="?status=boarded" class="text-sm px-3 py-1.5 rounded-lg <?= $statusFilter==='boarded' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' ?>">Boarded</a>
            <a href="?status=void" class="text-sm px-3 py-1.5 rounded-lg <?= $statusFilter==='void' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600' ?>">Void</a>
            <a href="<?= APP_URL ?>/admin/index.php" class="text-sm text-primary-600 hover:underline ml-2">← Dashboard</a>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Kode</th>
                        <th class="px-4 py-3 font-semibold">Penumpang</th>
                        <th class="px-4 py-3 font-semibold">Rute</th>
                        <th class="px-4 py-3 font-semibold">Berangkat</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($tickets as $t): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono font-medium"><?= e($t['kode_tiket']) ?></td>
                        <td class="px-4 py-3">
                            <div><?= e($t['nama_lengkap']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($t['no_hp']) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <?= e($t['asal']) ?> → <?= e($t['tujuan']) ?><br>
                            <span class="text-xs text-slate-400"><?= e($t['kode_kendaraan']) ?></span>
                        </td>
                        <td class="px-4 py-3"><?= date('d M H:i', strtotime($t['tanggal_berangkat'] . ' ' . $t['jam_berangkat'])) ?></td>
                        <td class="px-4 py-3"><?= statusBadge($t['status']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2 flex-wrap">
                                <a href="<?= APP_URL ?>/admin/cetak-tiket.php?id=<?= (int)$t['id'] ?>" target="_blank" class="text-xs bg-slate-700 text-white px-2 py-1 rounded-lg hover:bg-slate-800">Cetak</a>
                                <?php if ($t['status'] === 'active'): ?>
                                <a href="<?= APP_URL ?>/admin/scan.php?kode=<?= urlencode($t['kode_tiket']) ?>" class="text-xs bg-primary-600 text-white px-2 py-1 rounded-lg hover:bg-primary-700">Scan</a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    <button type="submit" name="send_warning" class="text-xs bg-amber-500 text-white px-2 py-1 rounded-lg hover:bg-amber-600">Peringatkan</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
