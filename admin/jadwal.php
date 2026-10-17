<?php
/**
 * Kelola Jadwal - CRUD lengkap
 * Tambah / Edit / Hapus / Update status
 */
$pageTitle = 'Kelola Jadwal';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Ambil semua routes untuk dropdown
$routes = $pdo->query("SELECT * FROM routes WHERE is_active = 1 ORDER BY jenis, asal")->fetchAll();

// ========== HAPUS ==========
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Cek apakah ada tiket aktif
    $cek = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE schedule_id = ? AND status IN ('pending','active','boarded')");
    $cek->execute([$id]);
    if ($cek->fetchColumn() > 0) {
        setFlash('danger', 'Tidak bisa hapus: masih ada tiket aktif/pending pada jadwal ini.');
    } else {
        $pdo->prepare("DELETE FROM schedules WHERE id = ?")->execute([$id]);
        setFlash('success', 'Jadwal berhasil dihapus.');
    }
    redirect(APP_URL . '/admin/jadwal.php');
}

// ========== SIMPAN (Tambah / Edit) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_jadwal'])) {
    $id            = (int)($_POST['id'] ?? 0);
    $route_id      = (int)($_POST['route_id'] ?? 0);
    $kode          = trim($_POST['kode_kendaraan'] ?? '');
    $nama          = trim($_POST['nama_kendaraan'] ?? '');
    $tgl_berangkat = $_POST['tanggal_berangkat'] ?? '';
    $jam_berangkat = $_POST['jam_berangkat'] ?? '';
    $tgl_tiba      = $_POST['tanggal_tiba'] ?? null;
    $jam_tiba      = $_POST['jam_tiba'] ?? null;
    $harga         = (float)($_POST['harga_idr'] ?? 0);
    $total_kursi   = max(1, (int)($_POST['total_kursi'] ?? 50));
    $status        = $_POST['status'] ?? 'scheduled';
    
    $allowedStatus = ['scheduled','boarding','departed','cancelled','completed'];
    if (!in_array($status, $allowedStatus)) $status = 'scheduled';
    
    if (!$route_id || !$kode || !$tgl_berangkat || !$jam_berangkat || $harga <= 0) {
        setFlash('danger', 'Lengkapi data wajib: Rute, Kode, Tanggal, Jam, Harga.');
        redirect(APP_URL . '/admin/jadwal.php' . ($id ? '?edit='.$id : '?tambah=1'));
    }
    
    $boarding_start = date('Y-m-d H:i:s', strtotime("$tgl_berangkat $jam_berangkat") - 30*60);
    
    if ($id > 0) {
        // Edit - jangan ubah kursi_tersedia secara kasar, hitung selisih
        $old = $pdo->prepare("SELECT total_kursi, kursi_tersedia FROM schedules WHERE id = ?");
        $old->execute([$id]);
        $oldData = $old->fetch();
        $terpakai = $oldData ? ($oldData['total_kursi'] - $oldData['kursi_tersedia']) : 0;
        $kursiBaru = max(0, $total_kursi - $terpakai);
        
        $stmt = $pdo->prepare("
            UPDATE schedules SET 
                route_id=?, kode_kendaraan=?, nama_kendaraan=?,
                tanggal_berangkat=?, jam_berangkat=?, tanggal_tiba=?, jam_tiba=?,
                harga_idr=?, total_kursi=?, kursi_tersedia=?, status=?, boarding_start=?
            WHERE id=?
        ");
        $stmt->execute([
            $route_id, $kode, $nama,
            $tgl_berangkat, $jam_berangkat, $tgl_tiba ?: null, $jam_tiba ?: null,
            $harga, $total_kursi, $kursiBaru, $status, $boarding_start, $id
        ]);
        
        // Jika status diubah ke departed → void tiket active
        if ($status === 'departed') {
            $stmt = $pdo->prepare("SELECT id, user_id, kode_tiket FROM tickets WHERE schedule_id = ? AND status = 'active' AND payment_status = 'paid'");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $tv) {
                $pdo->prepare("UPDATE tickets SET status = 'void' WHERE id = ?")->execute([$tv['id']]);
                createNotification($tv['user_id'], 'Tiket Hangus', "Tiket {$tv['kode_tiket']} hangus karena kendaraan sudah berangkat.", 'danger', $tv['id']);
            }
        }
        setFlash('success', 'Jadwal berhasil diupdate.');
    } else {
        // Tambah baru
        $stmt = $pdo->prepare("
            INSERT INTO schedules (route_id, kode_kendaraan, nama_kendaraan, tanggal_berangkat, jam_berangkat, 
                tanggal_tiba, jam_tiba, harga_idr, total_kursi, kursi_tersedia, status, boarding_start)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $route_id, $kode, $nama, $tgl_berangkat, $jam_berangkat,
            $tgl_tiba ?: null, $jam_tiba ?: null, $harga, $total_kursi, $total_kursi, $status, $boarding_start
        ]);
        setFlash('success', 'Jadwal baru berhasil ditambahkan.');
    }
    redirect(APP_URL . '/admin/jadwal.php');
}

// ========== UPDATE STATUS CEPAT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_id'], $_POST['new_status']) && !isset($_POST['simpan_jadwal'])) {
    $sid = (int)$_POST['schedule_id'];
    $status = $_POST['new_status'];
    $allowed = ['scheduled','boarding','departed','cancelled','completed'];
    if (in_array($status, $allowed)) {
        $pdo->prepare("UPDATE schedules SET status = ? WHERE id = ?")->execute([$status, $sid]);
        if ($status === 'departed') {
            $stmt = $pdo->prepare("SELECT id, user_id, kode_tiket FROM tickets WHERE schedule_id = ? AND status = 'active' AND payment_status = 'paid'");
            $stmt->execute([$sid]);
            foreach ($stmt->fetchAll() as $tv) {
                $pdo->prepare("UPDATE tickets SET status = 'void' WHERE id = ?")->execute([$tv['id']]);
                createNotification($tv['user_id'], 'Tiket Hangus', "Tiket {$tv['kode_tiket']} hangus karena kendaraan sudah berangkat.", 'danger', $tv['id']);
            }
        }
        setFlash('success', 'Status jadwal diupdate.');
    }
    redirect(APP_URL . '/admin/jadwal.php');
}

// Mode form
$editData = null;
$showForm = isset($_GET['tambah']) || isset($_GET['edit']);
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
    if (!$editData) {
        setFlash('danger', 'Jadwal tidak ditemukan.');
        redirect(APP_URL . '/admin/jadwal.php');
    }
}

// List jadwal
$stmt = $pdo->query("
    SELECT s.*, r.jenis, r.asal, r.tujuan
    FROM schedules s
    JOIN routes r ON s.route_id = r.id
    WHERE s.tanggal_berangkat >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    ORDER BY s.tanggal_berangkat DESC, s.jam_berangkat
");
$schedules = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Kelola Jadwal</h1>
            <p class="text-sm text-slate-500">Tambah, edit, hapus, dan update status jadwal</p>
        </div>
        <div class="flex gap-2">
            <a href="?tambah=1" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-4 py-2.5 rounded-xl transition text-sm">+ Tambah Jadwal</a>
            <a href="<?= APP_URL ?>/admin/index.php" class="text-sm text-primary-600 hover:underline self-center">← Dashboard</a>
        </div>
    </div>
    
    <?php if ($showForm): ?>
    <!-- Form Tambah / Edit -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="font-bold text-slate-800 mb-4"><?= $editData ? 'Edit Jadwal' : 'Tambah Jadwal Baru' ?></h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <input type="hidden" name="simpan_jadwal" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Rute *</label>
                    <select name="route_id" required class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="">-- Pilih Rute --</option>
                        <?php foreach ($routes as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($editData['route_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                            <?= strtoupper($r['jenis']) ?> • <?= e($r['asal']) ?> → <?= e($r['tujuan']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                        <?php foreach (['scheduled','boarding','departed','cancelled','completed'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($editData['status'] ?? 'scheduled') === $st ? 'selected' : '' ?>><?= strtoupper($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kode Kendaraan *</label>
                    <input type="text" name="kode_kendaraan" required value="<?= e($editData['kode_kendaraan'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none" placeholder="KA-120 / GA-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Kendaraan</label>
                    <input type="text" name="nama_kendaraan" value="<?= e($editData['nama_kendaraan'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none" placeholder="Argo Parahyangan">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Berangkat *</label>
                    <input type="date" name="tanggal_berangkat" required value="<?= e($editData['tanggal_berangkat'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Jam Berangkat *</label>
                    <input type="time" name="jam_berangkat" required value="<?= e(isset($editData['jam_berangkat']) ? substr($editData['jam_berangkat'],0,5) : '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Tiba</label>
                    <input type="date" name="tanggal_tiba" value="<?= e($editData['tanggal_tiba'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Jam Tiba</label>
                    <input type="time" name="jam_tiba" value="<?= e(isset($editData['jam_tiba']) && $editData['jam_tiba'] ? substr($editData['jam_tiba'],0,5) : '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Harga (IDR) *</label>
                    <input type="number" name="harga_idr" required min="1000" step="1000" value="<?= e($editData['harga_idr'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Total Kursi *</label>
                    <input type="number" name="total_kursi" required min="1" value="<?= e($editData['total_kursi'] ?? 50) ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-xl transition">
                    <?= $editData ? 'Simpan Perubahan' : 'Tambah Jadwal' ?>
                </button>
                <a href="<?= APP_URL ?>/admin/jadwal.php" class="border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-6 py-2.5 rounded-xl transition">Batal</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Tabel Jadwal -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Kendaraan</th>
                        <th class="px-4 py-3 font-semibold">Rute</th>
                        <th class="px-4 py-3 font-semibold">Keberangkatan</th>
                        <th class="px-4 py-3 font-semibold">Kursi</th>
                        <th class="px-4 py-3 font-semibold">Harga</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($schedules as $s): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= e($s['kode_kendaraan']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($s['nama_kendaraan']) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs uppercase text-slate-400"><?= e($s['jenis']) ?></span><br>
                            <?= e($s['asal']) ?> → <?= e($s['tujuan']) ?>
                        </td>
                        <td class="px-4 py-3">
                            <?= date('d M Y', strtotime($s['tanggal_berangkat'])) ?><br>
                            <span class="font-semibold"><?= date('H:i', strtotime($s['jam_berangkat'])) ?></span>
                        </td>
                        <td class="px-4 py-3"><?= $s['kursi_tersedia'] ?>/<?= $s['total_kursi'] ?></td>
                        <td class="px-4 py-3"><?= formatRupiah($s['harga_idr']) ?></td>
                        <td class="px-4 py-3">
                            <form method="POST" class="flex gap-1 items-center">
                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                <select name="new_status" class="text-xs border border-slate-300 rounded-lg px-2 py-1">
                                    <?php foreach (['scheduled','boarding','departed','completed','cancelled'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $s['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="text-xs bg-primary-600 text-white px-2 py-1 rounded-lg hover:bg-primary-700">OK</button>
                            </form>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a href="?edit=<?= $s['id'] ?>" class="text-xs bg-amber-500 text-white px-2 py-1 rounded-lg hover:bg-amber-600">Edit</a>
                                <a href="?hapus=<?= $s['id'] ?>" onclick="return confirm('Yakin hapus jadwal ini?')" class="text-xs bg-red-500 text-white px-2 py-1 rounded-lg hover:bg-red-600">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($schedules)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada jadwal. Klik + Tambah Jadwal.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 text-xs text-slate-500 bg-amber-50 border border-amber-200 rounded-xl p-3">
        <strong>Catatan:</strong> Jika status diubah ke <b>Departed</b>, semua tiket ACTIVE yang belum boarding akan otomatis HANGUS.
        Jadwal yang masih punya tiket aktif/pending tidak bisa dihapus.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
