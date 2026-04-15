/**
 * Consolidate PWA Installation & Service Worker Logic
 */
(function() {
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        console.log('PWA: Ready to install');
        
        // Auto-show popup after 2 seconds if not dismissed
        setTimeout(() => {
            if (!sessionStorage.getItem('pwa_dismissed')) {
                showPwaPrompt();
            }
        }, 2000);
    });

    window.showPwaPrompt = function() {
        const el = document.getElementById('pwa-install-prompt');
        // Hide if already in standalone mode
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) return;
        
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('animate-slide-up');
            
            // Set up button listeners once visible
            const btn = document.getElementById('pwa-install-btn');
            const close = document.getElementById('pwa-close');
            
            if (btn && !btn.dataset.bound) {
                btn.dataset.bound = "true";
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (isIOS) btn.innerText = "How to Install 📲";

                btn.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        deferredPrompt = null;
                        el.classList.add('hidden');
                    } else if (isIOS) {
                        alert("To install on iOS: \n1. Tap 'Share' (bottom center) \n2. Tap 'Add to Home Screen' ➕");
                    } else {
                        alert("Installation is already in progress or not supported by this browser. Try Chrome/Edge! 📱");
                    }
                });
            }

            if (close && !close.dataset.bound) {
                close.dataset.bound = "true";
                close.addEventListener('click', () => {
                    el.classList.add('hidden');
                    sessionStorage.setItem('pwa_dismissed', 'true');
                });
            }
        }
    };

    window.addEventListener('appinstalled', () => {
        const el = document.getElementById('pwa-install-prompt');
        if (el) el.classList.add('hidden');
        deferredPrompt = null;
    });

    // Register SW
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(APP_ROOT + 'sw.js?v=V17').catch(err => console.error('SW Error:', err));
    }
})();
