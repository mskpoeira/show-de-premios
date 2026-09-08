/**
 * Service Worker - Show de Prêmios
 * Suporte a Funcionamento Offline e Cache de Recursos
 */

const CACHE_NAME = 'showdepremios-v1';
const ASSETS_TO_CACHE = [
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/js/offline-sync.js',
    '/favicon.ico'
];

// Instalação: pré-armazena assets fundamentais
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE).catch(() => {
                // Silencioso se algum asset individual falhar
            });
        }).then(() => self.skipWaiting())
    );
});

// Ativação: limpa caches antigos
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Interceptação de requisições: Network first, fallback to Cache
self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Não intercepta chamadas POST ou métodos não-GET (o offline-sync.js cuidará delas via localStorage)
    if (req.method !== 'GET') {
        return;
    }

    // Não intercepta ping de conectividade
    if (req.url.includes('/api/ping')) {
        return;
    }

    event.respondWith(
        fetch(req)
            .then((res) => {
                // Se obteve resposta válida, salva cópia atualizada no cache
                if (res && res.status === 200 && res.type === 'basic') {
                    const resClone = res.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(req, resClone));
                }
                return res;
            })
            .catch(() => {
                // Falha de rede (offline): serve versão do cache
                return caches.match(req).then((cached) => {
                    if (cached) {
                        return cached;
                    }
                    // Se for navegação HTML e não tiver cache específico, tenta a raiz ou avisa
                    if (req.headers.get('accept')?.includes('text/html')) {
                        return caches.match('/').then((rootCached) => {
                            return rootCached || new Response(
                                '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Modo Offline</title><style>body{font-family:sans-serif;text-align:center;padding:3rem;background:#0f172a;color:#fff;}h1{color:#f59e0b;}</style></head><body><h1>⚡ Modo Offline Ativo</h1><p>Você está desconectado da internet. Suas páginas salvas e dados continuam disponíveis localmente.</p><button onclick="window.history.back()" style="padding:10px 20px;font-size:16px;cursor:pointer;">Voltar</button></body></html>',
                                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                            );
                        });
                    }
                });
            })
    );
});
