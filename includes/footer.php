</main>

<!-- Footer -->
<footer class="bg-slate-900 text-slate-300 mt-16" data-aos="fade-up" data-aos-duration="800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-9 h-9 bg-primary-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </div>
                    <span class="font-bold text-xl text-white"><?= APP_NAME ?></span>
                </div>
                <p class="text-sm text-slate-400 max-w-md leading-relaxed">
                    Platform pemesanan tiket kereta api & pesawat paling modern dan terpercaya. 
                    Pembayaran multi-currency, QRIS, Debit, dan Cash. Nikmati perjalanan dengan tenang.
                </p>
            </div>
            
            <div>
                <h4 class="font-semibold text-white mb-4">Layanan</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= APP_URL ?>/user/cari.php?jenis=kereta" class="hover:text-white transition">Tiket Kereta</a></li>
                    <li><a href="<?= APP_URL ?>/user/cari.php?jenis=pesawat" class="hover:text-white transition">Tiket Pesawat</a></li>
                    <li><a href="#" class="hover:text-white transition">Cek Status Tiket</a></li>
                    <li><a href="#" class="hover:text-white transition">Bantuan</a></li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-semibold text-white mb-4">Kontak</h4>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        support@tiketku.com
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        1500-123
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="border-t border-slate-800 mt-10 pt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-slate-500">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
            <p class="text-xs">Sistem pemesanan tiket modern • PHP Native</p>
        </div>
    </div>
</footer>

<!-- AOS.js -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
// Mobile menu
document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
    document.getElementById('mobile-menu')?.classList.toggle('hidden');
});

(function() {
    // ===== Progress bar pindah halaman =====
    const bar = document.getElementById('nprogress-bar');
    document.querySelectorAll('a[href]').forEach(function(a) {
        a.addEventListener('click', function() {
            const href = a.getAttribute('href') || '';
            if (!href || href.startsWith('#') || href.startsWith('javascript') || a.target === '_blank') return;
            if (href.indexOf('http') === 0 && href.indexOf(location.host) === -1) return;
            if (!bar) return;
            bar.style.width = '0%';
            bar.style.opacity = '1';
            requestAnimationFrame(function() { bar.style.width = '65%'; });
            setTimeout(function() { bar.style.width = '100%'; }, 200);
        });
    });

    // ===== Navbar animasi masuk =====
    var nav = document.querySelector('nav');
    if (nav) {
        nav.style.opacity = '0';
        nav.style.transform = 'translateY(-12px)';
        nav.style.transition = 'opacity 0.5s cubic-bezier(0.22,1,0.36,1), transform 0.5s cubic-bezier(0.22,1,0.36,1)';
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                nav.style.opacity = '1';
                nav.style.transform = 'translateY(0)';
            });
        });
    }

    // ===== Auto AOS ke SEMUA elemen konten =====
    var effects = ['fade-up', 'fade-up', 'fade-right', 'fade-left', 'zoom-in', 'fade-up', 'flip-up', 'zoom-in-up'];
    var selectors = [
        'main section',
        'main section > div',
        'main .bg-white.rounded-2xl',
        'main .bg-white.rounded-xl',
        'main .card-hover',
        'main .grid > *',
        'main form',
        'main table',
        'main thead',
        'main tbody tr',
        'main h1',
        'main h2',
        'main h3',
        'main .max-w-4xl',
        'main .max-w-6xl',
        'main .max-w-7xl > *',
        'main .max-w-md',
        'main .max-w-lg',
        'main .shadow-sm',
        'main .shadow-md',
        'main .shadow-lg',
        'main .border.rounded-xl',
        'main .border.rounded-2xl',
        'main label',
        'main .flex.gap-2',
        'main .flex.gap-3',
        'main .stats, main [class*="grid-cols"] > div'
    ];

    var idx = 0;
    var seen = new WeakSet();

    selectors.forEach(function(sel) {
        document.querySelectorAll(sel).forEach(function(el) {
            if (seen.has(el)) return;
            if (el.closest('nav')) return;
            if (el.hasAttribute('data-aos')) { seen.add(el); return; }
            // Hindari elemen terlalu kecil / ikon saja
            if (el.children.length === 0 && (el.textContent || '').trim().length < 2) return;

            var effect = effects[idx % effects.length];
            el.setAttribute('data-aos', effect);
            el.setAttribute('data-aos-duration', '750');
            el.setAttribute('data-aos-easing', 'ease-out-cubic');
            el.setAttribute('data-aos-delay', String(Math.min((idx % 6) * 80, 400)));
            el.setAttribute('data-aos-offset', '30');
            seen.add(el);
            idx++;
        });
    });

    // Hero text khusus
    document.querySelectorAll('.gradient-hero h1, .gradient-hero p, .gradient-hero a, .gradient-hero form').forEach(function(el, i) {
        if (!el.hasAttribute('data-aos')) {
            el.setAttribute('data-aos', i % 2 === 0 ? 'fade-right' : 'fade-left');
            el.setAttribute('data-aos-duration', '800');
            el.setAttribute('data-aos-delay', String(100 + i * 100));
        }
    });

    // Init AOS — full
    AOS.init({
        duration: 750,
        easing: 'ease-out-cubic',
        once: true,
        offset: 40,
        delay: 0,
        mirror: false,
        anchorPlacement: 'top-bottom',
        disable: false
    });

    // Refresh setelah gambar/font load
    window.addEventListener('load', function() {
        AOS.refresh();
    });
})();
</script>
</body>
</html>
