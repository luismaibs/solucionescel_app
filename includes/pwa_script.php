<script>
var PWABASE = '<?= $asset_base ?>';
</script>
<!-- PWA Installation Script -->
<script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(PWABASE + 'service-worker.js')
                .then((registration) => {
                    console.log('✅ Service Worker registrado:', registration.scope);

                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                console.log('🔄 Nueva versión disponible');
                                // Optionally show update notification
                                if (confirm('Nueva versión disponible. ¿Actualizar ahora?')) {
                                    newWorker.postMessage({ type: 'SKIP_WAITING' });
                                    window.location.reload();
                                }
                            }
                        });
                    });
                })
                .catch((error) => {
                    console.error('❌ Error al registrar Service Worker:', error);
                });
        });

        // Handle controller change
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        });
    }

    // PWA Install Prompt (sin botón flotante; la opción de instalar sigue en el menú de usuario)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
    });

    window.addEventListener('appinstalled', () => {
        console.log('✅ PWA instalada correctamente');
    });

    // Detect if running as PWA
    function isPWA() {
        return window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;
    }

    // Log PWA status
    if (isPWA()) {
        console.log('🚀 Ejecutando como PWA');

        // Add PWA class to body for custom styling
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('pwa-mode');
        });
    }
</script>

<!-- PWA Styles for standalone mode -->
<style>
    /* Hide elements when in PWA mode */
    body.pwa-mode {
        /* Additional padding for safe areas on mobile */
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
    }

    /* PWA Loading indicator */
    .pwa-loading {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #3b82f6, #2563eb);
        z-index: 99999;
        animation: loading 2s ease-in-out infinite;
    }

    @keyframes loading {

        0%,
        100% {
            transform: translateX(-100%);
        }

        50% {
            transform: translateX(100%);
        }
    }
</style>