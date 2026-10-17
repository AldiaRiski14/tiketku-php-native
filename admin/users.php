<?php
/**
 * Kelola User
 * - Daftar akun user
 * - Ban / Inactive / Aktifkan / Hapus
 * - Notifikasi in-app saja (tanpa email)
 *   * Admin: flash message konfirmasi
 *   * User: notifikasi muncul setelah akun aktif lagi & login
 */
$pageTitle = 'Kelola User';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Pastikan kolom status ada (graceful)
$hasStatusCol = false;
try {
    $pdo->query("SELECT account_status FROM users LIMIT 1");
    $hasStatusCol = true;
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['aksi'])) {
    $uid = (int)$_POST['user_id'];
    $aksi = $_POST['aksi'];
    $durasi = $_POST['durasi'] ?? '';
    $alasan = trim($_POST['alasan'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $target = $stmt->fetch();
    
    if (!$target) {
        setFlash('danger', 'User tidak ditemukan.');
        redirect(APP_URL . '/admin/users.php');
    }
    if ($target['role'] === 'admin') {
        setFlash('danger', 'Tidak bisa mengubah status akun admin.');
        redirect(APP_URL . '/admin/users.php');
    }
    if ($uid === (int)$_SESSION['user_id']) {
        setFlash('danger', 'Tidak bisa mengubah status akun sendiri.');
        redirect(APP_URL . '/admin/users.php');
    }
    if (!$hasStatusCol) {
        setFlash('danger', 'Kolom account_status belum ada. Jalankan migration_users.sql terlebih dahulu.');
        redirect(APP_URL . '/admin/users.php');
    }
    
    $until = null;
    $status = 'active';
    $durasiLabel = 'Permanen';
    if ($durasi === '7d') { $until = date('Y-m-d H:i:s', strtotime('+7 days')); $durasiLabel = '7 hari'; }
    elseif ($durasi === '30d') { $until = date('Y-m-d H:i:s', strtotime('+30 days')); $durasiLabel = '30 hari'; }
    elseif ($durasi === '90d') { $until = date('Y-m-d H:i:s', strtotime('+90 days')); $durasiLabel = '90 hari'; }
    
    $namaUser = $target['nama_lengkap'];
    $prevStatus = $target['account_status'] ?? 'active';
    $prevReason = $target['ban_reason'] ?? '';
    
    if ($aksi === 'hapus') {
        $cekTiket = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
        $cekTiket->execute([$uid]);
        $punyaTiket = $cekTiket->fetchColumn() > 0;
        
        if ($punyaTiket) {
            $alasanBan = $alasan ?: 'Akun dinonaktifkan permanen oleh admin';
            $pdo->prepare("UPDATE users SET account_status = 'banned', banned_until = NULL, ban_reason = ? WHERE id = ?")
                ->execute([$alasanBan, $uid]);
            // Disimpan untuk dibaca user nanti setelah dipulihkan
            createNotification($uid, 'Akun Diblokir', "Akun Anda telah diblokir permanen. Alasan: $alasanBan", 'danger');
            setFlash('success', "Akun \"{$namaUser}\" memiliki tiket — di-BAN permanen (tidak dihapus). Alasan: {$alasanBan}");
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$uid]);
            setFlash('success', "Akun \"{$namaUser}\" berhasil dihapus.");
        }
        redirect(APP_URL . '/admin/users.php');
    }
    
    if ($aksi === 'ban') {
        $pdo->prepare("UPDATE users SET account_status = 'banned', banned_until = ?, ban_reason = ? WHERE id = ?")
            ->execute([$until, $alasan ?: null, $uid]);
        $msgUntil = $until ? date('d M Y H:i', strtotime($until)) : 'Permanen';
        // Notifikasi user: baru kelihatan setelah akun dipulihkan & login
        createNotification($uid, 'Akun Diblokir (Banned)', "Akun Anda telah diblokir hingga {$msgUntil}." . ($alasan ? " Alasan: {$alasan}" : ''), 'danger');
        setFlash('success', "Akun \"{$namaUser}\" berhasil di-BAN selama {$durasiLabel}." . ($alasan ? " Alasan: {$alasan}" : ''));
        
    } elseif ($aksi === 'inactive') {
        $pdo->prepare("UPDATE users SET account_status = 'inactive', banned_until = ?, ban_reason = ? WHERE id = ?")
            ->execute([$until, $alasan ?: null, $uid]);
        $msgUntil = $until ? date('d M Y H:i', strtotime($until)) : 'Permanen';
        createNotification($uid, 'Akun Dinonaktifkan', "Akun Anda dinonaktifkan hingga {$msgUntil}." . ($alasan ? " Alasan: {$alasan}" : ''), 'warning');
        setFlash('success', "Akun \"{$namaUser}\" berhasil di-NONAKTIFKAN selama {$durasiLabel}." . ($alasan ? " Alasan: {$alasan}" : ''));
        
    } elseif ($aksi === 'aktifkan') {
        $pdo->prepare("UPDATE users SET account_status = 'active', banned_until = NULL, ban_reason = NULL WHERE id = ?")
            ->execute([$uid]);
        
        // Notifikasi untuk user setelah login
        if ($prevStatus === 'banned') {
            $detail = $prevReason ? " Sebelumnya akun Anda diblokir dengan alasan: {$prevReason}." : " Sebelumnya akun Anda dalam status diblokir.";
            createNotification($uid, 'Akun Berhasil Dipulihkan', "Selamat, akun Anda telah aktif kembali.{$detail} Anda dapat menggunakan layanan TiketKu seperti biasa.", 'success');
            setFlash('success', "Akun \"{$namaUser}\" berhasil DIPULIHKAN dari ban.");
        } elseif ($prevStatus === 'inactive') {
            $detail = $prevReason ? " Sebelumnya akun Anda dinonaktifkan dengan alasan: {$prevReason}." : " Sebelumnya akun Anda dalam status nonaktif.";
            createNotification($uid, 'Akun Aktif Kembali', "Selamat, akun Anda telah aktif kembali.{$detail} Anda dapat menggunakan layanan TiketKu seperti biasa.", 'success');
            setFlash('success', "Akun \"{$namaUser}\" berhasil DIAKTIFKAN kembali.");
        } else {
            createNotification($uid, 'Akun Aktif', 'Akun Anda dalam status aktif.', 'success');
            setFlash('success', "Akun \"{$namaUser}\" berhasil diaktifkan.");
        }
    }
    
    redirect(APP_URL . '/admin/users.php');
}

// Auto-aktifkan yang sudah lewat banned_until
if ($hasStatusCol) {
    $pdo->exec("UPDATE users SET account_status = 'active', banned_until = NULL, ban_reason = NULL 
                WHERE account_status IN ('banned','inactive') AND banned_until IS NOT NULL AND banned_until < NOW()");
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE role = 'user'";
$params = [];
if ($q) {
    $sql .= " AND (nama_lengkap LIKE ? OR email LIKE ? OR no_hp LIKE ?)";
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Kelola User</h1>
            <p class="text-sm text-slate-500">Ban, nonaktifkan, atau aktifkan kembali akun pengguna</p>
        </div>
        <a href="<?= APP_URL ?>/admin/index.php" class="text-sm text-primary-600 hover:underline">← Dashboard</a>
    </div>
    
    <?php if (!$hasStatusCol): ?>
    <div class="mb-5 bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 text-sm">
        <strong>Perlu migrasi database.</strong> Jalankan file <code>migration_users.sql</code> di phpMyAdmin agar fitur ban/inactive berfungsi.
    </div>
    <?php endif; ?>
    
    <!-- Search -->
    <form method="GET" class="mb-5 flex gap-2">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama, email, no HP..."
               class="flex-1 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
        <button type="submit" class="bg-primary-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">Cari</button>
    </form>
    
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">User</th>
                        <th class="px-4 py-3 font-semibold">Kontak</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Daftar</th>
                        <th class="px-4 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): 
                        $st = $hasStatusCol ? ($u['account_status'] ?? 'active') : 'active';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= e($u['nama_lengkap']) ?></div>
                            <div class="text-xs text-slate-400">ID: <?= $u['id'] ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div><?= e($u['email']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($u['no_hp'] ?: '-') ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($st === 'active'): ?>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">ACTIVE</span>
                            <?php elseif ($st === 'banned'): ?>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">BANNED</span>
                                <?php if (!empty($u['banned_until'])): ?>
                                <div class="text-xs text-slate-400 mt-0.5">sd <?= date('d M Y', strtotime($u['banned_until'])) ?></div>
                                <?php else: ?>
                                <div class="text-xs text-slate-400 mt-0.5">Permanen</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">INACTIVE</span>
                                <?php if (!empty($u['banned_until'])): ?>
                                <div class="text-xs text-slate-400 mt-0.5">sd <?= date('d M Y', strtotime($u['banned_until'])) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($u['ban_reason'])): ?>
                            <div class="text-xs text-slate-500 mt-0.5 max-w-[180px] truncate" title="<?= e($u['ban_reason']) ?>"><?= e($u['ban_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <?php if ($st === 'active'): ?>
                                <button type="button" onclick="openModal(<?= $u['id'] ?>, 'ban', '<?= e(addslashes($u['nama_lengkap'])) ?>')" class="text-xs bg-red-500 text-white px-2 py-1 rounded-lg hover:bg-red-600">Ban</button>
                                <button type="button" onclick="openModal(<?= $u['id'] ?>, 'inactive', '<?= e(addslashes($u['nama_lengkap'])) ?>')" class="text-xs bg-amber-500 text-white px-2 py-1 rounded-lg hover:bg-amber-600">Inactive</button>
                                <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="aksi" value="aktifkan">
                                    <button type="submit" class="text-xs bg-green-600 text-white px-2 py-1 rounded-lg hover:bg-green-700">Aktifkan</button>
                                </form>
                                <?php endif; ?>
                                <button type="button" onclick="openModal(<?= $u['id'] ?>, 'hapus', '<?= e(addslashes($u['nama_lengkap'])) ?>')" class="text-xs bg-slate-600 text-white px-2 py-1 rounded-lg hover:bg-slate-700">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Tidak ada user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ban / Inactive / Hapus -->
<div id="aksiModal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center p-4 modal-backdrop">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl modal-panel">
        <h3 id="modalTitle" class="font-bold text-lg text-slate-800 mb-4">Aksi User</h3>
        <form method="POST" id="aksiForm">
            <input type="hidden" name="user_id" id="modalUserId">
            <input type="hidden" name="aksi" id="modalAksi">
            
            <div id="durasiBox" class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Durasi</label>
                <select name="durasi" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm">
                    <option value="7d">7 hari</option>
                    <option value="30d">30 hari (1 bulan)</option>
                    <option value="90d">90 hari (3 bulan)</option>
                    <option value="permanent">Permanen</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Alasan (opsional)</label>
                <textarea name="alasan" rows="2" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm" placeholder="Contoh: pelanggaran aturan, spam, dll"></textarea>
            </div>
            
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-600 text-sm font-medium">Batal</button>
                <button type="submit" id="modalSubmit" class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold">Konfirmasi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(uid, aksi, nama) {
    document.getElementById('modalUserId').value = uid;
    document.getElementById('modalAksi').value = aksi;
    const title = document.getElementById('modalTitle');
    const durasiBox = document.getElementById('durasiBox');
    const btn = document.getElementById('modalSubmit');
    
    if (aksi === 'ban') {
        title.textContent = 'Ban: ' + nama;
        durasiBox.classList.remove('hidden');
        btn.className = 'px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold';
        btn.textContent = 'Ban Akun';
    } else if (aksi === 'inactive') {
        title.textContent = 'Nonaktifkan: ' + nama;
        durasiBox.classList.remove('hidden');
        btn.className = 'px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-semibold';
        btn.textContent = 'Nonaktifkan';
    } else {
        title.textContent = 'Hapus: ' + nama;
        durasiBox.classList.add('hidden');
        btn.className = 'px-4 py-2 rounded-xl bg-slate-700 text-white text-sm font-semibold';
        btn.textContent = 'Hapus Akun';
    }
    document.getElementById('aksiModal').classList.remove('hidden');
    document.getElementById('aksiModal').classList.add('flex');
}
function closeModal() {
    document.getElementById('aksiModal').classList.add('hidden');
    document.getElementById('aksiModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
