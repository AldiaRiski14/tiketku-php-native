<?php
$pageTitle = 'Pembayaran';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$ticket_id = (int)($_GET['ticket_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, s.kode_kendaraan, s.nama_kendaraan, s.tanggal_berangkat, s.jam_berangkat,
           r.asal, r.tujuan, r.jenis
    FROM tickets t
    JOIN schedules s ON t.schedule_id = s.id
    JOIN routes r ON s.route_id = r.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('danger', 'Tiket tidak ditemukan.');
    redirect(APP_URL . '/user/tiket-saya.php');
}

if ($ticket['payment_status'] === 'paid') {
    setFlash('info', 'Tiket ini sudah dibayar.');
    redirect(APP_URL . '/user/detail-tiket.php?id=' . $ticket_id);
}

// Proses pembayaran (simulasi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar'])) {
    $ref = 'PAY-' . strtoupper(substr(uniqid(), -8));
    
    $stmt = $pdo->prepare("
        UPDATE tickets 
        SET payment_status = 'paid', status = 'active', payment_ref = ?, updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$ref, $ticket_id, $_SESSION['user_id']]);
    
    // Notifikasi in-app
    createNotification(
        $_SESSION['user_id'],
        'Pembayaran Berhasil',
        "Tiket {$ticket['kode_tiket']} telah aktif. Tiket digital sedang dikirim ke email Anda. Jangan lupa scan barcode saat boarding!",
        'success',
        $ticket_id
    );
    
    // Redirect ke halaman sukses yang akan auto-kirim email via EmailJS
    redirect(APP_URL . '/user/sukses.php?ticket_id=' . $ticket_id);
}
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Pembayaran Tiket</h1>
    
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm text-slate-500">Kode Tiket</div>
                    <div class="font-bold text-lg text-slate-800"><?= e($ticket['kode_tiket']) ?></div>
                </div>
                <?= statusBadge($ticket['status']) ?>
            </div>
        </div>
        
        <div class="p-6 space-y-5">
            <!-- Ringkasan -->
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Rute</span>
                    <span class="font-medium"><?= e($ticket['asal']) ?> → <?= e($ticket['tujuan']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Kendaraan</span>
                    <span class="font-medium"><?= e($ticket['kode_kendaraan']) ?> • <?= e($ticket['nama_kendaraan']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Keberangkatan</span>
                    <span class="font-medium"><?= date('d M Y H:i', strtotime($ticket['tanggal_berangkat'] . ' ' . $ticket['jam_berangkat'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Jumlah Penumpang</span>
                    <span class="font-medium"><?= $ticket['jumlah_penumpang'] ?> orang</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Metode</span>
                    <span class="font-medium uppercase"><?= e($ticket['metode_pembayaran']) ?></span>
                </div>
            </div>
            
            <div class="border-t border-slate-100 pt-4">
                <div class="flex justify-between items-end">
                    <span class="text-slate-500">Total Pembayaran</span>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-primary-700">
                            <?php
                            $conv = convertFromIdr($ticket['total_harga_idr'], $ticket['currency_code']);
                            echo formatCurrency($conv['amount'], $ticket['currency_code'], $conv['symbol']);
                            ?>
                        </div>
                        <?php if ($ticket['currency_code'] !== 'IDR'): ?>
                        <div class="text-xs text-slate-400">(<?= formatRupiah($ticket['total_harga_idr']) ?>)</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Simulasi metode -->
            <?php if ($ticket['metode_pembayaran'] === 'qris'): ?>
            <div class="bg-slate-50 rounded-xl p-6 text-center">
                <p class="text-sm text-slate-600 mb-4">Scan QRIS berikut untuk membayar:</p>
                <div class="inline-block bg-white p-4 rounded-xl border-2 border-dashed border-slate-300">
                    <div id="qris-placeholder" class="w-48 h-48 bg-slate-100 flex items-center justify-center text-slate-400 text-sm">
                        [QRIS Simulation]<br>Scan di sini
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">Ini simulasi. Klik tombol di bawah untuk konfirmasi pembayaran.</p>
            </div>
            <?php elseif ($ticket['metode_pembayaran'] === 'debit'): ?>
            <div class="bg-slate-50 rounded-xl p-5 space-y-3">
                <p class="text-sm font-medium text-slate-700">Simulasi Kartu Debit</p>
                <input type="text" placeholder="Nomor Kartu" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" value="4111 1111 1111 1111">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" placeholder="MM/YY" class="border border-slate-300 rounded-lg px-3 py-2 text-sm" value="12/28">
                    <input type="text" placeholder="CVV" class="border border-slate-300 rounded-lg px-3 py-2 text-sm" value="123">
                </div>
            </div>
            <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
                <strong>Cash / Bayar di Loket:</strong> Setelah konfirmasi, silakan datang ke loket dengan menunjukkan kode tiket ini untuk pembayaran tunai. Status akan aktif setelah petugas konfirmasi (simulasi: klik bayar di bawah).
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <button type="submit" name="bayar" value="1" 
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-xl shadow-md hover:shadow-lg transition text-lg">
                    Konfirmasi Pembayaran
                </button>
            </form>
            
            <p class="text-xs text-center text-slate-400">
                Dengan menekan tombol di atas, Anda setuju dengan syarat & ketentuan pemesanan tiket.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
