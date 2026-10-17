<?php
/**
 * Halaman Sukses Pembayaran + Auto-kirim Tiket via EmailJS
 * Alur: bayar.php → update DB → redirect ke sini → EmailJS kirim tiket ke email user
 */
$pageTitle = 'Pembayaran Berhasil';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$ticket_id = (int)($_GET['ticket_id'] ?? 0);
$ticket = getTicketFullData($ticket_id);

if (!$ticket || $ticket['user_id'] != $_SESSION['user_id']) {
    setFlash('danger', 'Tiket tidak ditemukan.');
    redirect(APP_URL . '/user/tiket-saya.php');
}

if ($ticket['payment_status'] !== 'paid') {
    setFlash('warning', 'Tiket belum dibayar.');
    redirect(APP_URL . '/user/bayar.php?ticket_id=' . $ticket_id);
}

// Tandai email ticket akan dikirim (flag diupdate via AJAX setelah sukses EmailJS)
$alreadySent = (int)$ticket['email_ticket_sent'] === 1;

// Siapkan data penumpang untuk email
$passengerNames = [];
foreach ($ticket['passengers'] as $p) {
    $passengerNames[] = $p['nama_penumpang'];
}
$passengerList = implode(', ', $passengerNames);

$conv = convertFromIdr($ticket['total_harga_idr'], $ticket['currency_code']);
$totalFormatted = formatCurrency($conv['amount'], $ticket['currency_code'], $conv['symbol']);
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Success Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden text-center">
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-8 text-white">
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold">Pembayaran Berhasil!</h1>
            <p class="text-green-100 mt-2">Tiket Anda sudah aktif dan siap digunakan</p>
        </div>
        
        <div class="p-6 md:p-8 space-y-5">
            <div class="bg-slate-50 rounded-xl p-4 text-left space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Kode Tiket</span>
                    <span class="font-bold text-slate-800 font-mono"><?= e($ticket['kode_tiket']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Rute</span>
                    <span class="font-medium"><?= e($ticket['asal']) ?> → <?= e($ticket['tujuan']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Keberangkatan</span>
                    <span class="font-medium"><?= date('d M Y H:i', strtotime($ticket['tanggal_berangkat'] . ' ' . $ticket['jam_berangkat'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Total</span>
                    <span class="font-bold text-primary-700"><?= $totalFormatted ?></span>
                </div>
            </div>
            
            <!-- Email Status -->
            <div id="email-status" class="rounded-xl p-4 text-sm <?= $alreadySent ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-blue-50 text-blue-800 border border-blue-200' ?>">
                <?php if ($alreadySent): ?>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Tiket sudah dikirim ke email <strong><?= e($ticket['email']) ?></strong></span>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2" id="email-loading">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Mengirim tiket ke email <strong><?= e($ticket['email']) ?></strong> ...</span>
                    </div>
                    <div id="email-success" class="hidden flex items-center gap-2 text-green-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Tiket berhasil dikirim ke <strong><?= e($ticket['email']) ?></strong></span>
                    </div>
                    <div id="email-error" class="hidden text-red-700"></div>
                <?php endif; ?>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left text-sm text-amber-900">
                <p class="font-semibold mb-1">Penting!</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>Cek inbox / folder spam email Anda untuk tiket digital.</li>
                    <li>Tiket berstatus <strong>ACTIVE</strong>. Wajib scan barcode saat naik.</li>
                    <li>Sistem akan kirim <strong>peringatan email otomatis</strong> ±10 menit sebelum keberangkatan jika Anda belum boarding.</li>
                    <li>Datang minimal 30-45 menit sebelumnya.</li>
                </ul>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <a href="<?= APP_URL ?>/user/detail-tiket.php?id=<?= $ticket_id ?>" 
                   class="flex-1 bg-primary-600 hover:bg-primary-700 text-white font-bold py-3.5 rounded-xl transition text-center">
                    Lihat Tiket & QR Code
                </a>
                <a href="<?= APP_URL ?>/user/tiket-saya.php" 
                   class="flex-1 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold py-3.5 rounded-xl transition text-center">
                    Tiket Saya
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!$alreadySent): ?>
<!-- EmailJS SDK -->
<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script>
(function() {
    // Konfigurasi EmailJS (dari config PHP)
    const PUBLIC_KEY   = '<?= e(EMAILJS_PUBLIC_KEY) ?>';
    const SERVICE_ID   = '<?= e(EMAILJS_SERVICE_ID) ?>';
    const TEMPLATE_ID  = '<?= e(EMAILJS_TEMPLATE_TICKET) ?>';
    
    // Data yang dikirim ke template EmailJS
    // Pastikan di dashboard EmailJS template Anda pakai variabel yang sama ({{kode_tiket}}, {{nama_user}}, dll)
    const templateParams = {
        to_email:       '<?= e($ticket['email']) ?>',
        to_name:        '<?= e($ticket['nama_lengkap']) ?>',
        from_name:      'TiketKu',
        subject:        'Tiket Anda - <?= e($ticket['kode_tiket']) ?> | TiketKu',
        kode_tiket:     '<?= e($ticket['kode_tiket']) ?>',
        nama_user:      '<?= e($ticket['nama_lengkap']) ?>',
        rute:           '<?= e($ticket['asal']) ?> → <?= e($ticket['tujuan']) ?>',
        kendaraan:      '<?= e($ticket['kode_kendaraan']) ?> - <?= e($ticket['nama_kendaraan']) ?>',
        tanggal:        '<?= date('d M Y', strtotime($ticket['tanggal_berangkat'])) ?>',
        jam_berangkat:  '<?= date('H:i', strtotime($ticket['jam_berangkat'])) ?>',
        stasiun_asal:   '<?= e($ticket['stasiun_bandara_asal']) ?>',
        stasiun_tujuan: '<?= e($ticket['stasiun_bandara_tujuan']) ?>',
        penumpang:      '<?= e($passengerList) ?>',
        jumlah:         '<?= (int)$ticket['jumlah_penumpang'] ?>',
        total:          '<?= e($totalFormatted) ?>',
        metode:         '<?= strtoupper(e($ticket['metode_pembayaran'])) ?>',
        barcode:        '<?= e($ticket['barcode_data']) ?>',
        pesan_penting:  'Wajib scan barcode saat boarding. Sistem akan mengirim peringatan 10 menit sebelum keberangkatan jika belum naik. Tiket hangus jika kendaraan berangkat tanpa scan.'
    };
    
    function markEmailSent(success, message) {
        // Update flag di database via AJAX
        fetch('<?= APP_URL ?>/process/mark_email.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ticket_id=<?= $ticket_id ?>&type=ticket&success=' + (success ? '1' : '0') + '&msg=' + encodeURIComponent(message || '')
        }).catch(console.error);
    }
    
    // Init & Send
    if (PUBLIC_KEY === 'YOUR_EMAILJS_PUBLIC_KEY' || !PUBLIC_KEY) {
        // Belum dikonfigurasi → tampilkan petunjuk
        document.getElementById('email-loading').classList.add('hidden');
        document.getElementById('email-error').classList.remove('hidden');
        document.getElementById('email-error').innerHTML = `
            <strong>EmailJS belum dikonfigurasi.</strong><br>
            Edit file <code>config/database.php</code> dan isi EMAILJS_PUBLIC_KEY, SERVICE_ID, TEMPLATE_TICKET.<br>
            Lihat README untuk panduan lengkap. Tiket tetap aktif, Anda bisa lihat di halaman detail.
        `;
        return;
    }
    
    emailjs.init({ publicKey: PUBLIC_KEY });
    
    emailjs.send(SERVICE_ID, TEMPLATE_ID, templateParams)
        .then(function(response) {
            document.getElementById('email-loading').classList.add('hidden');
            document.getElementById('email-success').classList.remove('hidden');
            markEmailSent(true, 'OK: ' + response.status);
        }, function(error) {
            document.getElementById('email-loading').classList.add('hidden');
            document.getElementById('email-error').classList.remove('hidden');
            document.getElementById('email-error').innerHTML = 'Gagal mengirim email: ' + (error.text || JSON.stringify(error)) + 
                '<br><button onclick="location.reload()" class="mt-2 underline text-sm">Coba lagi</button>';
            markEmailSent(false, error.text || 'failed');
        });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
