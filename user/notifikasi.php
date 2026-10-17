<?php
$pageTitle = 'Notifikasi';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

// Mark all as read
if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    redirect(APP_URL . '/user/notifikasi.php');
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$_SESSION['user_id']]);
$notifs = $stmt->fetchAll();

// Mark individual as read on view
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$_SESSION['user_id']]);
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Notifikasi</h1>
        <?php if (!empty($notifs)): ?>
        <a href="?read_all=1" class="text-sm text-primary-600 hover:underline">Tandai semua dibaca</a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($notifs)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
        Belum ada notifikasi.
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($notifs as $n): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-4 <?= !$n['is_read'] ? 'border-l-4 border-l-primary-500' : '' ?>">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                    <?= $n['tipe']==='warning' ? 'bg-amber-100 text-amber-600' : '' ?>
                    <?= $n['tipe']==='danger'  ? 'bg-red-100 text-red-600' : '' ?>
                    <?= $n['tipe']==='success' ? 'bg-green-100 text-green-600' : '' ?>
                    <?= $n['tipe']==='info'    ? 'bg-blue-100 text-blue-600' : '' ?>
                ">
                    <?php if ($n['tipe'] === 'warning'): ?>⚠️
                    <?php elseif ($n['tipe'] === 'danger'): ?>🚫
                    <?php elseif ($n['tipe'] === 'success'): ?>✓
                    <?php else: ?>ℹ️
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-slate-800"><?= e($n['judul']) ?></div>
                    <p class="text-sm text-slate-600 mt-0.5"><?= e($n['pesan']) ?></p>
                    <div class="text-xs text-slate-400 mt-2"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
