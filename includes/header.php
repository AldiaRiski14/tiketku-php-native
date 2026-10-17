<?php
require_once __DIR__ . '/functions.php';
checkTicketWarningsAndVoid(); // auto cek peringatan & void

$currentUser = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
}

$unreadNotif = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadNotif = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        accent: {
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <!-- AOS.js - Animate On Scroll -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); }
        .gradient-hero {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
        }
        html { scroll-behavior: smooth; }

        /* Progress bar pindah halaman */
        #nprogress-bar {
            position: fixed; top: 0; left: 0; height: 3px; width: 0;
            background: linear-gradient(90deg, #2563eb, #60a5fa);
            z-index: 99999;
            transition: width 0.35s ease;
            box-shadow: 0 0 8px rgb(37 99 235 / 0.5);
            pointer-events: none;
        }

        /* Hover kartu & tombol */
        .card-hover {
            transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), box-shadow 0.3s ease !important;
        }
        .card-hover:hover {
            transform: translateY(-6px) !important;
            box-shadow: 0 20px 40px -12px rgb(37 99 235 / 0.22) !important;
        }
        a[class*="bg-primary"], a[class*="bg-green"],
        button[class*="bg-primary"], button[class*="bg-green"],
        button[type="submit"] {
            transition: transform 0.2s ease, box-shadow 0.25s ease, filter 0.2s ease !important;
        }
        a[class*="bg-primary"]:hover, a[class*="bg-green"]:hover,
        button[class*="bg-primary"]:hover, button[class*="bg-green"]:hover,
        button[type="submit"]:hover {
            transform: translateY(-2px) scale(1.02) !important;
            filter: brightness(1.05);
            box-shadow: 0 10px 24px -6px rgb(37 99 235 / 0.4) !important;
        }
        a[class*="bg-primary"]:active, button[type="submit"]:active {
            transform: scale(0.97) !important;
        }
        nav a { transition: all 0.2s ease !important; }
        nav a:hover { transform: translateY(-1px); }

        .flash-anim {
            animation: flashIn 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes flashIn {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        input:focus, select:focus, textarea:focus {
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.18) !important;
        }
        .modal-backdrop { animation: fadeIn 0.25s ease both; }
        .modal-panel { animation: modalIn 0.35s cubic-bezier(0.22,1,0.36,1) both; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        #mobile-menu:not(.hidden) {
            animation: menuSlide 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes menuSlide {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">
<div id="nprogress-bar"></div>

<!-- Navbar -->
<nav class="bg-white/90 backdrop-blur-md border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <!-- Logo -->
            <a href="<?= APP_URL ?>/index.php" class="flex items-center gap-2 group">
                <div class="w-10 h-10 bg-gradient-to-br from-primary-600 to-primary-800 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-105 transition">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-xl text-primary-800 tracking-tight"><?= APP_NAME ?></span>
                    <span class="hidden sm:inline text-xs text-slate-500 block -mt-1">Travel Easy</span>
                </div>
            </a>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center gap-1">
                <a href="<?= APP_URL ?>/index.php" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-700 hover:bg-primary-50 transition">Beranda</a>
                <a href="<?= APP_URL ?>/user/cari.php" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-700 hover:bg-primary-50 transition">Cari Tiket</a>
                <?php if (isLoggedIn()): ?>
                    <a href="<?= APP_URL ?>/user/tiket-saya.php" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-700 hover:bg-primary-50 transition">Tiket Saya</a>
                    <a href="<?= APP_URL ?>/user/notifikasi.php" class="relative px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-700 hover:bg-primary-50 transition">
                        Notifikasi
                        <?php if ($unreadNotif > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center"><?= $unreadNotif ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="<?= APP_URL ?>/admin/index.php" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary-100 text-primary-800 hover:bg-primary-200 transition">Admin Panel</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Auth Buttons -->
            <div class="flex items-center gap-3">
                <?php if (isLoggedIn()): ?>
                    <div class="hidden sm:flex items-center gap-2 text-sm">
                        <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center text-primary-700 font-semibold">
                            <?= strtoupper(substr($currentUser['nama_lengkap'], 0, 1)) ?>
                        </div>
                        <span class="font-medium text-slate-700"><?= e(explode(' ', $currentUser['nama_lengkap'])[0]) ?></span>
                    </div>
                    <a href="<?= APP_URL ?>/process/logout.php" class="text-sm font-medium text-slate-500 hover:text-red-600 transition">Logout</a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/user/login.php" class="text-sm font-medium text-slate-600 hover:text-primary-700 transition">Masuk</a>
                    <a href="<?= APP_URL ?>/user/register.php" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 rounded-xl shadow-md hover:shadow-lg transition">Daftar</a>
                <?php endif; ?>
                
                <!-- Mobile menu button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-white">
        <div class="px-4 py-3 space-y-1">
            <a href="<?= APP_URL ?>/index.php" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-primary-50">Beranda</a>
            <a href="<?= APP_URL ?>/user/cari.php" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-primary-50">Cari Tiket</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?= APP_URL ?>/user/tiket-saya.php" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-primary-50">Tiket Saya</a>
                <a href="<?= APP_URL ?>/user/notifikasi.php" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-primary-50">Notifikasi <?= $unreadNotif > 0 ? "($unreadNotif)" : '' ?></a>
                <?php if (isAdmin()): ?>
                    <a href="<?= APP_URL ?>/admin/index.php" class="block px-3 py-2 rounded-lg text-sm font-medium text-primary-700 bg-primary-50">Admin Panel</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Flash Message -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="max-w-7xl mx-auto px-4 mt-4 flash-anim">
    <div class="rounded-xl p-4 flex items-start gap-3 shadow-sm
        <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : '' ?>
        <?= $flash['type'] === 'danger'  ? 'bg-red-50 text-red-800 border border-red-200' : '' ?>
        <?= $flash['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' ?>
        <?= $flash['type'] === 'info'    ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' ?>
    ">
        <div class="flex-1 text-sm font-medium"><?= e($flash['message']) ?></div>
        <button onclick="this.closest('.flash-anim').remove()" class="text-current opacity-60 hover:opacity-100 transition">&times;</button>
    </div>
</div>
<?php endif; ?>

<main class="flex-1">
