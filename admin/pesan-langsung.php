<?php
/**
 * Pembelian Tiket Langsung di Tempat (Walk-in / Counter)
 * - Admin isi data penumpang sesuai KTP
 * - Bayar: Cash / QRIS / Debit di tempat
 * - Tiket langsung ACTIVE, bisa dicetak
 * - Tidak kirim email (karena bayar di loket)
 */
$pageTitle = 'Pesan Langsung (Counter)';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$error = '';
$successTicketId = null;

// Ambil jadwal yang masih tersedia
$stmt = $pdo->query("
    SELECT s.*, r.jenis, r.asal, r.tujuan, r.stasiun_bandara_asal, r.stasiun_bandara_tujuan
    FROM schedules s
    JOIN routes r ON s.route_id = r.id
    WHERE s.status IN ('scheduled','boarding')
      AND TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) > NOW()
      AND s.kursi_tersedia > 0
    ORDER BY s.tanggal_berangkat, s.jam_berangkat
");
$schedules = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
    $jumlah = max(1, min(6, (int)($_POST['jumlah_penumpang'] ?? 1)));
    $penumpang = $_POST['penumpang'] ?? [];
    $metode = $_POST['metode_pembayaran'] ?? 'cash';
    
    if (!in_array($metode, ['cash', 'qris', 'debit'])) {
        $metode = 'cash';
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM schedules 
        WHERE id = ? AND kursi_tersedia >= ? 
          AND status IN ('scheduled','boarding') 
          AND TIMESTAMP(tanggal_berangkat, jam_berangkat) > NOW()
    ");
    $stmt->execute([$schedule_id, $jumlah]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        $error = 'Jadwal tidak valid atau kursi tidak cukup.';
    } elseif (count($penumpang) < $jumlah) {
        $error = 'Data penumpang tidak lengkap.';
    } else {
        $valid = true;
        foreach ($penumpang as $p) {
            if (empty(trim($p['nama'] ?? '')) || empty(trim($p['identitas'] ?? ''))) {
                $valid = false;
                break;
            }
        }
        if (!$valid) {
            $error = 'Nama dan No. Identitas (KTP/Passport) wajib diisi untuk semua penumpang.';
        } else {
            $totalIdr = $schedule['harga_idr'] * $jumlah;
            $kodeTiket = generateTicketCode();
            $barcode = generateBarcodeData($kodeTiket);
            $adminId = $_SESSION['user_id'];
            $ref = strtoupper($metode) . '-' . strtoupper(substr(uniqid(), -6));
            
            try {
                $pdo->beginTransaction();
                
                // Cek apakah kolom purchase_channel ada
                $hasChannel = false;
                try {
                    $pdo->query("SELECT purchase_channel FROM tickets LIMIT 1");
                    $hasChannel = true;
                } catch (Exception $e) { /* kolom belum ada */ }
                
                if ($hasChannel) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets (
                            kode_tiket, user_id, schedule_id, jumlah_penumpang, total_harga_idr, 
                            currency_code, total_harga_currency, status, metode_pembayaran, 
                            payment_status, payment_ref, purchase_channel, barcode_data, email_ticket_sent
                        ) VALUES (?, ?, ?, ?, ?, 'IDR', ?, 'active', ?, 'paid', ?, 'walkin', ?, 1)
                    ");
                    $stmt->execute([
                        $kodeTiket, $adminId, $schedule_id, $jumlah, $totalIdr, $totalIdr,
                        $metode, $ref, $barcode
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets (
                            kode_tiket, user_id, schedule_id, jumlah_penumpang, total_harga_idr, 
                            currency_code, total_harga_currency, status, metode_pembayaran, 
                            payment_status, payment_ref, barcode_data, email_ticket_sent
                        ) VALUES (?, ?, ?, ?, ?, 'IDR', ?, 'active', ?, 'paid', ?, ?, 1)
                    ");
                    $stmt->execute([
                        $kodeTiket, $adminId, $schedule_id, $jumlah, $totalIdr, $totalIdr,
                        $metode, $ref, $barcode
                    ]);
                }
                $ticketId = $pdo->lastInsertId();
                
                $stmtP = $pdo->prepare("INSERT INTO passengers (ticket_id, nama_penumpang, no_identitas, tipe_identitas) VALUES (?, ?, ?, ?)");
                foreach ($penumpang as $p) {
                    $stmtP->execute([
                        $ticketId,
                        trim($p['nama']),
                        trim($p['identitas']),
                        $p['tipe'] ?? 'ktp'
                    ]);
                }
                
                $pdo->prepare("UPDATE schedules SET kursi_tersedia = kursi_tersedia - ? WHERE id = ?")
                    ->execute([$jumlah, $schedule_id]);
                
                $pdo->commit();
                $successTicketId = $ticketId;
                setFlash('success', "Tiket $kodeTiket berhasil dibuat (" . strtoupper($metode) . "). Silakan cetak tiket.");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal membuat tiket: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Pesan Langsung (Counter)</h1>
            <p class="text-sm text-slate-500 mt-1">Pembelian di loket • Admin isi data KTP • Bayar Cash/QRIS/Debit • Langsung cetak</p>
        </div>
        <a href="<?= APP_URL ?>/admin/index.php" class="text-sm text-primary-600 hover:underline">← Dashboard</a>
    </div>
    
    <?php if ($error): ?>
    <div class="mb-5 bg-red-50 text-red-700 text-sm rounded-xl px-4 py-3 border border-red-200"><?= e($error) ?></div>
    <?php endif; ?>
    
    <?php if ($successTicketId): ?>
    <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="font-bold text-green-800">Tiket berhasil dibuat!</div>
            <p class="text-sm text-green-700 mt-1">Status: ACTIVE • Channel: WALK-IN (Langsung)</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?= APP_URL ?>/admin/cetak-tiket.php?id=<?= $successTicketId ?>" target="_blank"
               class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-5 py-2.5 rounded-xl transition">
                🖨️ Cetak Tiket
            </a>
            <a href="<?= APP_URL ?>/admin/pesan-langsung.php" class="border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-5 py-2.5 rounded-xl transition">
                Pesan Lagi
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="counterForm" class="space-y-6">
        <!-- 1. Pilih Jadwal -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-4">1. Pilih Jadwal</h2>
            <select name="schedule_id" id="schedule_id" required
                    class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none">
                <option value="">-- Pilih Jadwal --</option>
                <?php foreach ($schedules as $s): ?>
                <option value="<?= $s['id'] ?>" data-harga="<?= $s['harga_idr'] ?>" data-kursi="<?= $s['kursi_tersedia'] ?>">
                    <?= strtoupper($s['jenis']) ?> • <?= e($s['asal']) ?> → <?= e($s['tujuan']) ?> • 
                    <?= date('d M Y H:i', strtotime($s['tanggal_berangkat'] . ' ' . $s['jam_berangkat'])) ?> • 
                    <?= e($s['kode_kendaraan']) ?> • Rp <?= number_format($s['harga_idr'], 0, ',', '.') ?> 
                    (sisa <?= $s['kursi_tersedia'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- 2. Data Penumpang -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-4">2. Data Penumpang (sesuai KTP / Identitas)</h2>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Jumlah Penumpang</label>
                <select name="jumlah_penumpang" id="jumlah" class="w-full max-w-xs border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?> orang</option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div id="penumpangContainer" class="space-y-4"></div>
            
            <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                ⚠️ Nama & nomor identitas harus sesuai dokumen resmi (KTP / Passport). Data ini akan tercetak di tiket.
            </div>
        </div>
        
        <!-- 3. Metode Pembayaran -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-4">3. Metode Pembayaran (di Tempat)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="cash" class="peer sr-only" checked>
                    <div class="border-2 border-slate-200 peer-checked:border-green-600 peer-checked:bg-green-50 rounded-xl p-4 text-center transition">
                        <div class="text-2xl mb-1">💵</div>
                        <div class="font-semibold text-slate-800">Cash</div>
                        <div class="text-xs text-slate-500">Tunai di loket</div>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="qris" class="peer sr-only">
                    <div class="border-2 border-slate-200 peer-checked:border-green-600 peer-checked:bg-green-50 rounded-xl p-4 text-center transition">
                        <div class="text-2xl mb-1">📱</div>
                        <div class="font-semibold text-slate-800">QRIS</div>
                        <div class="text-xs text-slate-500">Scan di tempat</div>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="debit" class="peer sr-only">
                    <div class="border-2 border-slate-200 peer-checked:border-green-600 peer-checked:bg-green-50 rounded-xl p-4 text-center transition">
                        <div class="text-2xl mb-1">💳</div>
                        <div class="font-semibold text-slate-800">Debit / Kartu</div>
                        <div class="text-xs text-slate-500">EDC di loket</div>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- 4. Ringkasan -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-3">4. Ringkasan</h2>
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm text-slate-500">Jenis Pembelian</div>
                    <div class="font-semibold text-slate-800">🏪 Langsung di Tempat (Walk-in)</div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-slate-500">Total</div>
                    <div id="totalDisplay" class="text-2xl font-bold text-primary-700">Rp 0</div>
                </div>
            </div>
        </div>
        
        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-xl shadow-md transition text-lg">
            Buat Tiket & Aktifkan
        </button>
    </form>
</div>

<script>
const jumlahSelect = document.getElementById('jumlah');
const scheduleSelect = document.getElementById('schedule_id');
const container = document.getElementById('penumpangContainer');
const totalDisplay = document.getElementById('totalDisplay');

function getHarga() {
    const opt = scheduleSelect.options[scheduleSelect.selectedIndex];
    return opt && opt.dataset.harga ? parseFloat(opt.dataset.harga) : 0;
}

function updateTotal() {
    const jumlah = parseInt(jumlahSelect.value) || 1;
    const total = getHarga() * jumlah;
    totalDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
    renderPenumpang(jumlah);
}

function renderPenumpang(jumlah) {
    let html = '';
    for (let i = 1; i <= jumlah; i++) {
        html += `
        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
            <div class="text-sm font-semibold text-slate-700 mb-3">Penumpang ${i}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Nama Lengkap (sesuai KTP) *</label>
                    <input type="text" name="penumpang[${i}][nama]" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="Nama sesuai identitas">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Tipe Identitas *</label>
                    <select name="penumpang[${i}][tipe]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="ktp">KTP</option>
                        <option value="passport">Passport</option>
                        <option value="sim">SIM</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">No. Identitas *</label>
                    <input type="text" name="penumpang[${i}][identitas]" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="Nomor KTP / Passport">
                </div>
            </div>
        </div>`;
    }
    container.innerHTML = html;
}

jumlahSelect.addEventListener('change', updateTotal);
scheduleSelect.addEventListener('change', updateTotal);
updateTotal();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
