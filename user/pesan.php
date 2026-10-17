<?php
$pageTitle = 'Pesan Tiket';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$schedule_id = (int)($_GET['schedule_id'] ?? 0);
if (!$schedule_id) {
    setFlash('danger', 'Jadwal tidak valid.');
    redirect(APP_URL . '/user/cari.php');
}

$stmt = $pdo->prepare("
    SELECT s.*, r.jenis, r.asal, r.tujuan, r.stasiun_bandara_asal, r.stasiun_bandara_tujuan
    FROM schedules s
    JOIN routes r ON s.route_id = r.id
    WHERE s.id = ? 
      AND s.status IN ('scheduled','boarding')
      AND TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) > NOW()
");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch();

if (!$schedule) {
    setFlash('danger', 'Jadwal tidak ditemukan atau sudah tidak tersedia (waktu keberangkatan sudah lewat).');
    redirect(APP_URL . '/user/cari.php');
}

$currencies = getCurrencies();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jumlah = max(1, min(10, (int)($_POST['jumlah_penumpang'] ?? 1)));
    $currency = $_POST['currency'] ?? 'IDR';
    $metode = $_POST['metode_pembayaran'] ?? '';
    $penumpang = $_POST['penumpang'] ?? [];
    
    if (!in_array($metode, ['cash','qris','debit'])) {
        $error = 'Pilih metode pembayaran yang valid.';
    } elseif ($jumlah > $schedule['kursi_tersedia']) {
        $error = 'Kursi tersedia tidak mencukupi.';
    } elseif (count($penumpang) < $jumlah) {
        $error = 'Data penumpang tidak lengkap.';
    } else {
        // Validasi nama penumpang
        $valid = true;
        foreach ($penumpang as $p) {
            if (empty(trim($p['nama'] ?? ''))) {
                $valid = false;
                break;
            }
        }
        if (!$valid) {
            $error = 'Semua nama penumpang wajib diisi.';
        } else {
            // Proses booking
            $totalIdr = $schedule['harga_idr'] * $jumlah;
            $conv = convertFromIdr($totalIdr, $currency);
            $kodeTiket = generateTicketCode();
            $barcode = generateBarcodeData($kodeTiket);
            
            try {
                $pdo->beginTransaction();
                
                // Insert ticket (online)
                // Coba dengan purchase_channel, fallback jika kolom belum ada
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets (kode_tiket, user_id, schedule_id, jumlah_penumpang, total_harga_idr, 
                            currency_code, total_harga_currency, status, metode_pembayaran, payment_status, 
                            purchase_channel, barcode_data)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid', 'online', ?)
                    ");
                    $stmt->execute([
                        $kodeTiket, $_SESSION['user_id'], $schedule_id, $jumlah, $totalIdr,
                        $currency, $conv['amount'], $metode, $barcode
                    ]);
                } catch (Exception $ex) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets (kode_tiket, user_id, schedule_id, jumlah_penumpang, total_harga_idr, 
                            currency_code, total_harga_currency, status, metode_pembayaran, payment_status, barcode_data)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid', ?)
                    ");
                    $stmt->execute([
                        $kodeTiket, $_SESSION['user_id'], $schedule_id, $jumlah, $totalIdr,
                        $currency, $conv['amount'], $metode, $barcode
                    ]);
                }
                $ticketId = $pdo->lastInsertId();
                
                // Insert passengers
                $stmtP = $pdo->prepare("INSERT INTO passengers (ticket_id, nama_penumpang, no_identitas, tipe_identitas) VALUES (?, ?, ?, ?)");
                foreach ($penumpang as $p) {
                    $stmtP->execute([
                        $ticketId,
                        trim($p['nama']),
                        trim($p['identitas'] ?? ''),
                        $p['tipe'] ?? 'ktp'
                    ]);
                }
                
                // Kurangi kursi
                $stmt = $pdo->prepare("UPDATE schedules SET kursi_tersedia = kursi_tersedia - ? WHERE id = ? AND kursi_tersedia >= ?");
                $stmt->execute([$jumlah, $schedule_id, $jumlah]);
                
                $pdo->commit();
                
                // Redirect ke halaman pembayaran
                redirect(APP_URL . '/user/bayar.php?ticket_id=' . $ticketId);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal memproses pemesanan. Silakan coba lagi.';
            }
        }
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mb-6">Pesan Tiket</h1>
    
    <!-- Schedule Summary -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6 shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-primary-600"><?= transportIcon($schedule['jenis']) ?></span>
            <span class="text-sm font-bold uppercase text-slate-500"><?= e($schedule['jenis']) ?></span>
            <span class="text-sm text-slate-600">• <?= e($schedule['kode_kendaraan']) ?> • <?= e($schedule['nama_kendaraan']) ?></span>
        </div>
        <div class="flex flex-wrap items-center gap-6 text-sm">
            <div>
                <span class="text-slate-400">Dari</span>
                <div class="font-semibold text-slate-800"><?= e($schedule['asal']) ?></div>
                <div class="text-xs text-slate-400"><?= e($schedule['stasiun_bandara_asal']) ?></div>
            </div>
            <div class="text-slate-300">→</div>
            <div>
                <span class="text-slate-400">Ke</span>
                <div class="font-semibold text-slate-800"><?= e($schedule['tujuan']) ?></div>
                <div class="text-xs text-slate-400"><?= e($schedule['stasiun_bandara_tujuan']) ?></div>
            </div>
            <div>
                <span class="text-slate-400">Berangkat</span>
                <div class="font-semibold text-slate-800"><?= date('d M Y', strtotime($schedule['tanggal_berangkat'])) ?> • <?= date('H:i', strtotime($schedule['jam_berangkat'])) ?></div>
            </div>
            <div>
                <span class="text-slate-400">Harga / orang</span>
                <div class="font-bold text-primary-700"><?= formatRupiah($schedule['harga_idr']) ?></div>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
    <div class="mb-5 bg-red-50 text-red-700 text-sm rounded-xl px-4 py-3 border border-red-200"><?= e($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" id="bookingForm" class="space-y-6">
        <!-- Jumlah & Currency -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-4">Detail Pemesanan</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Jumlah Penumpang</label>
                    <select name="jumlah_penumpang" id="jumlah" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                        <?php for ($i=1; $i<=min(6, $schedule['kursi_tersedia']); $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?> orang</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Mata Uang</label>
                    <select name="currency" id="currency" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                        <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>" data-rate="<?= $c['rate_to_idr'] ?>" data-symbol="<?= e($c['symbol']) ?>">
                            <?= e($c['code']) ?> - <?= e($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Total Estimasi</label>
                    <div id="totalDisplay" class="text-xl font-bold text-primary-700 py-2"><?= formatRupiah($schedule['harga_idr']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Data Penumpang -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-4">Data Penumpang</h2>
            <div id="penumpangContainer" class="space-y-4">
                <!-- Generated by JS -->
            </div>
        </div>
        
        <!-- Metode Pembayaran -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-semibold text-slate-800 mb-4">Metode Pembayaran</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="qris" class="peer sr-only" checked>
                    <div class="border-2 border-slate-200 peer-checked:border-primary-600 peer-checked:bg-primary-50 rounded-xl p-4 text-center transition">
                        <div class="text-2xl mb-1">📱</div>
                        <div class="font-semibold text-slate-800">QRIS</div>
                        <div class="text-xs text-slate-500">Scan & Bayar</div>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="debit" class="peer sr-only">
                    <div class="border-2 border-slate-200 peer-checked:border-primary-600 peer-checked:bg-primary-50 rounded-xl p-4 text-center transition">
                        <div class="text-2xl mb-1">💳</div>
                        <div class="font-semibold text-slate-800">Debit / Kartu</div>
                        <div class="text-xs text-slate-500">Visa / Mastercard</div>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="cash" class="peer sr-only">
                    <div class="border-2 border-slate-200 peer-checked:border-primary-600 peer-checked:bg-primary-50 rounded-xl p-4 text-center transition">
                        <div class="text-2xl mb-1">💵</div>
                        <div class="font-semibold text-slate-800">Cash</div>
                        <div class="text-xs text-slate-500">Bayar di loket</div>
                    </div>
                </label>
            </div>
        </div>
        
        <div class="flex gap-3">
            <a href="<?= APP_URL ?>/user/cari.php" class="px-6 py-3 rounded-xl border border-slate-300 text-slate-600 font-medium hover:bg-slate-50 transition">Kembali</a>
            <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 rounded-xl shadow-md hover:shadow-lg transition">
                Lanjut ke Pembayaran
            </button>
        </div>
    </form>
</div>

<script>
const hargaIdr = <?= (float)$schedule['harga_idr'] ?>;
const jumlahSelect = document.getElementById('jumlah');
const currencySelect = document.getElementById('currency');
const totalDisplay = document.getElementById('totalDisplay');
const container = document.getElementById('penumpangContainer');

function updateTotal() {
    const jumlah = parseInt(jumlahSelect.value);
    const opt = currencySelect.options[currencySelect.selectedIndex];
    const rate = parseFloat(opt.dataset.rate) || 1;
    const symbol = opt.dataset.symbol || 'Rp';
    const totalIdr = hargaIdr * jumlah;
    const converted = totalIdr / rate;
    
    if (opt.value === 'IDR') {
        totalDisplay.textContent = 'Rp ' + totalIdr.toLocaleString('id-ID');
    } else {
        totalDisplay.textContent = symbol + ' ' + converted.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    renderPenumpang(jumlah);
}

function renderPenumpang(jumlah) {
    let html = '';
    for (let i = 1; i <= jumlah; i++) {
        html += `
        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
            <div class="text-sm font-semibold text-slate-700 mb-3">Penumpang ${i}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-1">
                    <label class="block text-xs text-slate-500 mb-1">Nama Lengkap *</label>
                    <input type="text" name="penumpang[${i}][nama]" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="Sesuai identitas">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Tipe Identitas</label>
                    <select name="penumpang[${i}][tipe]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="ktp">KTP</option>
                        <option value="passport">Passport</option>
                        <option value="sim">SIM</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">No. Identitas</label>
                    <input type="text" name="penumpang[${i}][identitas]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="Nomor KTP/Passport">
                </div>
            </div>
        </div>`;
    }
    container.innerHTML = html;
}

jumlahSelect.addEventListener('change', updateTotal);
currencySelect.addEventListener('change', updateTotal);
updateTotal();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
