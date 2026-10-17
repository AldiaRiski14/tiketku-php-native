<?php
/**
 * Halaman Cetak Tiket (Print-friendly)
 * Bisa diakses admin setelah scan atau setelah buat tiket counter
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$ticket = getTicketFullData($id);

if (!$ticket) {
    die('Tiket tidak ditemukan.');
}

$conv = convertFromIdr($ticket['total_harga_idr'], $ticket['currency_code']);
$totalFormatted = formatCurrency($conv['amount'], $ticket['currency_code'], $conv['symbol']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Tiket - <?= e($ticket['kode_tiket']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 20px;
        }
        .no-print {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
        }
        .no-print button, .no-print a {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-print { background: #2563eb; color: white; }
        .btn-back { background: #e2e8f0; color: #334155; }
        .ticket {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .ticket-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .ticket-header h1 { font-size: 22px; font-weight: 700; }
        .ticket-header .code { font-size: 18px; font-weight: 700; letter-spacing: 1px; margin-top: 4px; }
        .badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .ticket-body { padding: 28px; }
        .route {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .route .city { font-size: 20px; font-weight: 700; }
        .route .station { font-size: 12px; color: #64748b; margin-top: 2px; }
        .route .mid { text-align: center; flex: 1; padding: 0 16px; }
        .route .mid .line { height: 2px; background: #cbd5e1; position: relative; margin: 8px 0; }
        .route .mid .line::before, .route .mid .line::after {
            content: '';
            position: absolute;
            top: -4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
        }
        .route .mid .line::before { left: 0; }
        .route .mid .line::after { right: 0; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-grid .label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid .value { font-size: 14px; font-weight: 600; margin-top: 2px; }
        .passengers {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .passengers h3 { font-size: 13px; color: #64748b; margin-bottom: 10px; }
        .passengers .p-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .passengers .p-row:last-child { border-bottom: none; }
        .passengers .num {
            width: 24px; height: 24px;
            background: #dbeafe; color: #1d4ed8;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        .barcode-box {
            text-align: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .barcode-box img, .barcode-box canvas { max-width: 140px; }
        .barcode-text { font-size: 10px; color: #94a3b8; margin-top: 6px; font-family: monospace; word-break: break-all; }
        .total { font-size: 18px; font-weight: 700; color: #1e40af; }
        .meta { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .ticket { box-shadow: none; border: 1px solid #ccc; border-radius: 0; }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Tiket</button>
        <a href="<?= APP_URL ?>/admin/scan.php" class="btn-back">← Kembali ke Scan</a>
        <a href="<?= APP_URL ?>/admin/tiket.php" class="btn-back">Daftar Tiket</a>
    </div>
    
    <div class="ticket">
        <div class="ticket-header">
            <div>
                <h1>TiketKu</h1>
                <div class="code"><?= e($ticket['kode_tiket']) ?></div>
            </div>
            <div style="text-align:right">
                <span class="badge"><?= e($ticket['status']) ?></span>
                <div style="margin-top:8px;font-size:12px;opacity:0.9"><?= strtoupper(e($ticket['jenis'])) ?></div>
            </div>
        </div>
        
        <div class="ticket-body">
            <div class="route">
                <div>
                    <div class="city"><?= e($ticket['asal']) ?></div>
                    <div class="station"><?= e($ticket['stasiun_bandara_asal']) ?></div>
                </div>
                <div class="mid">
                    <div style="font-size:12px;color:#64748b"><?= e($ticket['kode_kendaraan']) ?></div>
                    <div class="line"></div>
                    <div style="font-size:11px;color:#94a3b8"><?= e($ticket['nama_kendaraan']) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="city"><?= e($ticket['tujuan']) ?></div>
                    <div class="station"><?= e($ticket['stasiun_bandara_tujuan']) ?></div>
                </div>
            </div>
            
            <div class="info-grid">
                <div>
                    <div class="label">Tanggal</div>
                    <div class="value"><?= date('d M Y', strtotime($ticket['tanggal_berangkat'])) ?></div>
                </div>
                <div>
                    <div class="label">Berangkat</div>
                    <div class="value"><?= date('H:i', strtotime($ticket['jam_berangkat'])) ?></div>
                </div>
                <div>
                    <div class="label">Tiba</div>
                    <div class="value"><?= $ticket['jam_tiba'] ? date('H:i', strtotime($ticket['jam_tiba'])) : '-' ?></div>
                </div>
                <div>
                    <div class="label">Penumpang</div>
                    <div class="value"><?= (int)$ticket['jumlah_penumpang'] ?> orang</div>
                </div>
            </div>
            
            <div class="passengers">
                <h3>Data Penumpang</h3>
                <?php foreach ($ticket['passengers'] as $i => $p): ?>
                <div class="p-row">
                    <span class="num"><?= $i + 1 ?></span>
                    <div style="flex:1">
                        <strong><?= e($p['nama_penumpang']) ?></strong>
                        <span style="color:#94a3b8;margin-left:8px"><?= strtoupper(e($p['tipe_identitas'])) ?> • <?= e($p['no_identitas'] ?: '-') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="footer-row">
                <div class="barcode-box">
                    <div id="qrcode"></div>
                    <div class="barcode-text"><?= e($ticket['barcode_data']) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="total"><?= $totalFormatted ?></div>
                    <div class="meta">
                        <?= strtoupper(e($ticket['metode_pembayaran'] ?? '-')) ?> • <?= e($ticket['payment_ref'] ?? '-') ?><br>
                        <?= e($ticket['payment_status']) ?>
                        <?php if ($ticket['boarded_at']): ?>
                        <br>Boarded: <?= date('d M Y H:i', strtotime($ticket['boarded_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= e($ticket['barcode_data']) ?>",
            width: 120,
            height: 120,
            colorDark: "#1e40af",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Auto print jika ada param ?auto=1
        if (new URLSearchParams(window.location.search).get('auto') === '1') {
            setTimeout(() => window.print(), 400);
        }
    </script>
</body>
</html>
