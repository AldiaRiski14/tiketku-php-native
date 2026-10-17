<?php
$pageTitle = 'Daftar';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (empty($nama) || empty($email) || empty($password)) {
        $error = 'Nama, email, dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $password2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nama_lengkap, email, password, no_hp) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $email, $hash, $no_hp]);
            
            setFlash('success', 'Registrasi berhasil! Silakan masuk.');
            redirect(APP_URL . '/user/login.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-8 py-6 text-white text-center">
                <h1 class="text-2xl font-bold">Buat Akun Baru</h1>
                <p class="text-blue-100 text-sm mt-1">Daftar gratis di <?= APP_NAME ?></p>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?>
                <div class="mb-5 bg-red-50 text-red-700 text-sm rounded-xl px-4 py-3 border border-red-200">
                    <?= e($error) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required value="<?= e($_POST['nama_lengkap'] ?? '') ?>"
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none"
                               placeholder="Nama sesuai identitas">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none"
                               placeholder="email@contoh.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">No. HP</label>
                        <input type="text" name="no_hp" value="<?= e($_POST['no_hp'] ?? '') ?>"
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none"
                               placeholder="08xxxxxxxxxx">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                        <input type="password" name="password" required
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none"
                               placeholder="Minimal 6 karakter">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Konfirmasi Password</label>
                        <input type="password" name="password2" required
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none"
                               placeholder="Ulangi password">
                    </div>
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3.5 rounded-xl shadow-md hover:shadow-lg transition mt-2">
                        Daftar Sekarang
                    </button>
                </form>
                
                <div class="mt-6 text-center text-sm text-slate-500">
                    Sudah punya akun? 
                    <a href="<?= APP_URL ?>/user/login.php" class="text-primary-600 font-semibold hover:underline">Masuk</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
