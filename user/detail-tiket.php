<?php
$pageTitle = 'Detail Tiket';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, s.kode_kendaraan, s.nama_kendaraan, s.tanggal_berangkat, s.jam_berangkat, s.jam_tiba,
           s.status as schedule_status, s.boarding_start,
           r.asal, r.tujuan, r.jenis, r.stasiun_bandara_asal, r.stasiun_bandara_tujuan
    FROM tickets t
    JOIN schedules s ON t.schedule_id = s.id
    JOIN routes r ON s.route_id = r.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$ticket = $stmt->fetch();
// t.* sudah mencakup email_ticket_sent, email_warning_sent, warning_sent

if (!$ticket) {
    setFlash('danger', 'Tiket tidak ditemukan.');
    redirect(APP_URL . '/user/tiket-saya.php');
}

// Ambil penumpang
$stmt = $pdo->prepare("SELECT * FROM passengers WHERE ticket_id = ?");
$stmt->execute([$id]);
$passengers = $stmt->fetchAll();

$departTime = strtotime($ticket['tanggal_berangkat'] . ' ' . $ticket['jam_berangkat']);
$now = time();
$minutesLeft = round(($departTime - $now) / 60);
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Detail Tiket</h1>
        <a href="<?= APP_URL ?>/user/tiket-saya.php" class="text-sm text-primary-600 hover:underline">← Kembali</a>
    </div>
    
    <!-- Status Alert -->
    <?php if ($ticket['status'] === 'void'): ?>
    <div class="mb-5 bg-red-100 border border-red-300 text-red-800 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <div class="font-bold">Tiket Hangus (VOID)</div>
            <p class="text-sm mt-1">Anda tidak melakukan boarding sebelum keberangkatan. Tiket ini sudah tidak berlaku.</p>
        </div>
    </div>
    <?php elseif ($ticket['status'] === 'active' && $minutesLeft > 0 && $minutesLeft <= 45): ?>
    <div class="mb-5 bg-amber-100 border border-amber-300 text-amber-900 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <div class="font-bold">Peringatan Keberangkatan!</div>
            <p class="text-sm mt-1">Kendaraan akan berangkat dalam <strong><?= $minutesLeft ?> menit</strong>. Segera ke area boarding dan minta admin scan barcode tiket Anda!</p>
        </div>
    </div>
    <?php elseif ($ticket['status'] === 'boarded'): ?>
    <div class="mb-5 bg-green-100 border border-green-300 text-green-800 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <div class="font-bold">Sudah Boarding ✓</div>
            <p class="text-sm mt-1">Anda telah terkonfirmasi naik kendaraan pada <?= date('d M Y H:i', strtotime($ticket['boarded_at'])) ?>. Selamat perjalanan!</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Header Ticket -->
        <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-5">
            <div class="flex justify-between items-start">
                <div>
                    <div class="text-primary-200 text-sm">Kode Tiket</div>
                    <div class="text-2xl font-bold tracking-wide"><?= e($ticket['kode_tiket']) ?></div>
                </div>
                <div class="text-right">
                    <?= statusBadge($ticket['status']) ?>
                    <div class="text-xs text-primary-200 mt-2 uppercase"><?= e($ticket['jenis']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Route -->
            <div class="flex items-center justify-between mb-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-slate-800"><?= e($ticket['asal']) ?></div>
                    <div class="text-xs text-slate-400 mt-1"><?= e($ticket['stasiun_bandara_asal']) ?></div>
                </div>
                <div class="flex-1 px-4 text-center">
                    <div class="text-sm font-medium text-slate-600"><?= e($ticket['kode_kendaraan']) ?></div>
                    <div class="h-px bg-slate-300 my-2 relative">
                        <div class="absolute left-1/2 -translate-x-1/2 -top-1.5 w-3 h-3 rounded-full bg-primary-500"></div>
                    </div>
                    <div class="text-xs text-slate-400"><?= e($ticket['nama_kendaraan']) ?></div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-slate-800"><?= e($ticket['tujuan']) ?></div>
                    <div class="text-xs text-slate-400 mt-1"><?= e($ticket['stasiun_bandara_tujuan']) ?></div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-6">
                <div>
                    <div class="text-slate-400 text-xs">Tanggal</div>
                    <div class="font-semibold"><?= date('d M Y', strtotime($ticket['tanggal_berangkat'])) ?></div>
                </div>
                <div>
                    <div class="text-slate-400 text-xs">Berangkat</div>
                    <div class="font-semibold"><?= date('H:i', strtotime($ticket['jam_berangkat'])) ?></div>
                </div>
                <div>
                    <div class="text-slate-400 text-xs">Tiba</div>
                    <div class="font-semibold"><?= $ticket['jam_tiba'] ? date('H:i', strtotime($ticket['jam_tiba'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-slate-400 text-xs">Penumpang</div>
                    <div class="font-semibold"><?= $ticket['jumlah_penumpang'] ?> orang</div>
                </div>
            </div>
            
            <!-- QR Code -->
            <?php if (in_array($ticket['status'], ['active', 'boarded'])): ?>
            <div class="bg-slate-50 rounded-xl p-6 text-center mb-6">
                <p class="text-sm font-medium text-slate-600 mb-3">Tunjukkan QR Code ini saat boarding</p>
                <div id="qrcode" class="inline-block bg-white p-3 rounded-xl shadow-sm"></div>
                <p class="text-xs text-slate-400 mt-3 font-mono"><?= e($ticket['barcode_data']) ?></p>
                <?php if ($ticket['status'] === 'active'): ?>
                <p class="text-xs text-amber-600 mt-2 font-medium">⚠️ Wajib di-scan admin sebelum keberangkatan!</p>
                <?php endif; ?>
            </div>
            <script>
                new QRCode(document.getElementById("qrcode"), {
                    text: "<?= e($ticket['barcode_data']) ?>",
                    width: 180,
                    height: 180,
                    colorDark: "#1e40af",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            </script>
            <?php endif; ?>
            
            <!-- Penumpang -->
            <div class="border-t border-slate-100 pt-5">
                <h3 class="font-semibold text-slate-800 mb-3">Daftar Penumpang</h3>
                <div class="space-y-2">
                    <?php foreach ($passengers as $i => $p): ?>
                    <div class="flex items-center gap-3 text-sm bg-slate-50 rounded-lg px-4 py-2.5">
                        <span class="w-6 h-6 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-xs font-bold"><?= $i+1 ?></span>
                        <div class="flex-1">
                            <div class="font-medium text-slate-800"><?= e($p['nama_penumpang']) ?></div>
                            <div class="text-xs text-slate-400"><?= strtoupper($p['tipe_identitas']) ?> • <?= e($p['no_identitas'] ?: '-') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Payment Info -->
            <div class="border-t border-slate-100 pt-5 mt-5 text-sm">
                <div class="flex justify-between mb-1">
                    <span class="text-slate-500">Total</span>
                    <span class="font-bold text-primary-700">
                        <?php
                        $conv = convertFromIdr($ticket['total_harga_idr'], $ticket['currency_code']);
                        echo formatCurrency($conv['amount'], $ticket['currency_code'], $conv['symbol']);
                        ?>
                    </span>
                </div>
                <div class="flex justify-between text-xs text-slate-400">
                    <span>Pembayaran: <?= strtoupper($ticket['metode_pembayaran'] ?? '-') ?> • <?= e($ticket['payment_ref'] ?? '-') ?></span>
                    <span><?= statusBadge($ticket['payment_status']) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Aturan -->
    <div class="mt-6 bg-slate-100 rounded-xl p-5 text-sm text-slate-600">
        <h4 class="font-semibold text-slate-800 mb-2">Aturan Penting</h4>
        <ul class="list-disc list-inside space-y-1 text-xs">
            <li>Tiket aktif wajib di-scan barcode oleh petugas saat naik kendaraan.</li>
            <li>Sistem mengirim <strong>peringatan otomatis ±10 menit</strong> sebelum keberangkatan (in-app + email) jika belum boarding.</li>
            <li>Jika kendaraan sudah berangkat dan status masih ACTIVE → tiket otomatis HANGUS.</li>
            <li>Datang minimal 30-45 menit sebelum jadwal untuk proses boarding.</li>
        </ul>
    </div>
</div>

<?php
// Auto-kirim email peringatan jika mendekati keberangkatan (<=10 menit) dan belum pernah dikirim
$warningMinutes = (int)getSetting('warning_minutes_before', 10);
$shouldSendWarningEmail = (
    $ticket['status'] === 'active' &&
    $minutesLeft > 0 &&
    $minutesLeft <= $warningMinutes &&
    empty($ticket['email_warning_sent'])
);

// Ambil email user
$userEmail = '';
$stmtU = $pdo->prepare("SELECT email, nama_lengkap FROM users WHERE id = ?");
$stmtU->execute([$_SESSION['user_id']]);
$u = $stmtU->fetch();
if ($u) $userEmail = $u['email'];
?>

<?php if ($shouldSendWarningEmail && $userEmail): ?>
<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script>
(function() {
    const PUBLIC_KEY  = '<?= e(EMAILJS_PUBLIC_KEY) ?>';
    const SERVICE_ID  = '<?= e(EMAILJS_SERVICE_ID) ?>';
    const TEMPLATE_ID = '<?= e(EMAILJS_TEMPLATE_WARNING) ?>';
    
    if (PUBLIC_KEY === 'YOUR_EMAILJS_PUBLIC_KEY' || !PUBLIC_KEY) return;
    
    const params = {
        to_email:      '<?= e($userEmail) ?>',
        to_name:       '<?= e($u['nama_lengkap'] ?? '') ?>',
        from_name:     'TiketKu Alert',
        subject:       'PERINGATAN: Tiket <?= e($ticket['kode_tiket']) ?> berangkat dalam <?= $minutesLeft ?> menit!',
        kode_tiket:    '<?= e($ticket['kode_tiket']) ?>',
        nama_user:     '<?= e($u['nama_lengkap'] ?? '') ?>',
        rute:          '<?= e($ticket['asal']) ?> → <?= e($ticket['tujuan']) ?>',
        kendaraan:     '<?= e($ticket['kode_kendaraan']) ?>',
        menit_tersisa: '<?= $minutesLeft ?>',
        pesan:         'Segera ke area boarding dan scan barcode tiket Anda sekarang juga agar tidak hangus!'
    };
    
    emailjs.init({ publicKey: PUBLIC_KEY });
    emailjs.send(SERVICE_ID, TEMPLATE_ID, params)
        .then(function() {
            fetch('<?= APP_URL ?>/process/mark_email.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ticket_id=<?= $id ?>&type=warning&success=1'
            });
        }).catch(console.error);
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
