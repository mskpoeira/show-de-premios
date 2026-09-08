/**
 * Offline Sync Manager - Show de Prêmios
 * Garante funcionamento contínuo sem internet e sincronização automática
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'showdepremios_pending_sales';
    let isOnline = navigator.onLine;

    // 1. Registra Service Worker se suportado
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').then((reg) => {
                // Service worker registrado
            }).catch((err) => {
                console.warn('Falha ao registrar Service Worker:', err);
            });
        });
    }

    // 2. Banner Visual de Conectividade
    function createConnectivityBanner() {
        if (document.getElementById('connectivity-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'connectivity-banner';
        banner.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 99999;
            padding: 0.65rem 1rem;
            text-align: center;
            font-weight: 800;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            transition: all 0.3s ease;
        `;
        document.body.appendChild(banner);
        updateBannerState();
    }

    function updateBannerState() {
        const banner = document.getElementById('connectivity-banner');
        if (!banner) return;

        const pendingCount = getPendingSales().length;

        if (!navigator.onLine) {
            banner.style.display = 'block';
            banner.style.background = '#f59e0b';
            banner.style.color = '#78350f';
            banner.innerHTML = `⚠️ <strong>MODO OFFLINE ATIVO:</strong> Você está sem internet. O sistema continua funcionando normalmente e seus dados estão salvos localmente no computador! ${pendingCount > 0 ? `(${pendingCount} envio(s) pendente(s))` : ''}`;
        } else if (pendingCount > 0) {
            banner.style.display = 'block';
            banner.style.background = '#3b82f6';
            banner.style.color = '#ffffff';
            banner.innerHTML = `🔄 <strong>SINCRONIZANDO:</strong> Conexão ativa! Enviando ${pendingCount} operação(ões) pendente(s) para o servidor...`;
        } else {
            // Online e sem pendências: esconde
            banner.style.display = 'none';
        }
    }

    // 3. Fila no LocalStorage
    function getPendingSales() {
        try {
            const data = localStorage.getItem(STORAGE_KEY);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            return [];
        }
    }

    function savePendingSales(list) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (e) {
            console.error('Erro ao gravar no localStorage:', e);
        }
        updateBannerState();
    }

    function addPendingSale(formDataObj) {
        const list = getPendingSales();
        list.push({
            id: 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
            timestamp: new Date().toISOString(),
            data: formDataObj
        });
        savePendingSales(list);
    }

    // 4. Toast de Notificação
    function showNotification(message, type = 'success') {
        const toast = document.createElement('div');
        const bg = type === 'success' ? '#10b981' : (type === 'warning' ? '#f59e0b' : '#ef4444');
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100000;
            background: ${bg};
            color: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
            max-width: 420px;
        `;
        toast.innerHTML = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    }

    // 5. Sincronização Automática
    let isSyncing = false;
    async function syncPendingData() {
        if (isSyncing || !navigator.onLine) return;
        const list = getPendingSales();
        if (list.length === 0) return;

        isSyncing = true;
        updateBannerState();

        const remaining = [];
        let syncedCount = 0;

        for (const item of list) {
            try {
                const formData = new FormData();
                for (const [key, value] of Object.entries(item.data)) {
                    formData.append(key, value);
                }
                formData.append('_ajax', '1');

                const response = await fetch('/rodadas/salvar-vendas', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                if (response.ok) {
                    syncedCount++;
                } else {
                    remaining.push(item);
                }
            } catch (err) {
                // Erro de rede durante sync, mantém na fila
                remaining.push(item);
            }
        }

        savePendingSales(remaining);
        isSyncing = false;
        updateBannerState();

        if (syncedCount > 0) {
            showNotification(`🎉 <strong>SINCRONIZADO:</strong> ${syncedCount} rodada(s)/venda(s) enviadas com sucesso para o servidor!`, 'success');
            
            // Atualiza badge de status se estiver na tela da rodada
            const badge = document.getElementById('round_status_badge');
            if (badge) {
                badge.textContent = '⚡ RODADA EM ANDAMENTO';
                badge.style.background = '#2563eb';
                badge.style.color = '#fff';
            }
        }
    }

    // 6. Interceptação do Formulário de Vendas
    function setupSalesFormInterceptor() {
        const form = document.getElementById('salesForm');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            // Se estiver offline ou se o envio falhar, salva localmente
            if (!navigator.onLine) {
                e.preventDefault();
                handleOfflineSubmit(form);
                return;
            }

            // Se estiver online, tenta submeter via AJAX/Fetch primeiro
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Salvando...';
            }

            const formData = new FormData(form);
            formData.append('_ajax', '1');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                if (response.ok) {
                    const result = await response.json().catch(() => ({}));
                    showNotification('✅ ' + (result.message || 'Vendas e premiações salvas com sucesso! Status: EM ANDAMENTO.'), 'success');
                    
                    const badge = document.getElementById('round_status_badge');
                    if (badge) {
                        badge.textContent = '⚡ RODADA EM ANDAMENTO';
                        badge.style.background = '#2563eb';
                        badge.style.color = '#fff';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                } else {
                    // Erro de resposta, guarda localmente por garantia
                    handleOfflineSubmit(form);
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                }
            } catch (networkError) {
                // Queda de rede durante o submit: salva offline
                handleOfflineSubmit(form);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    }

    function handleOfflineSubmit(form) {
        const formData = new FormData(form);
        const obj = {};
        for (const [key, val] of formData.entries()) {
            obj[key] = val;
        }

        addPendingSale(obj);
        showNotification('🟡 <strong>MODO OFFLINE:</strong> Vendas salvas com segurança no seu computador! Elas serão enviadas automaticamente assim que a conexão retornar.', 'warning');

        const badge = document.getElementById('round_status_badge');
        if (badge) {
            badge.textContent = '⚡ RODADA EM ANDAMENTO (OFFLINE)';
            badge.style.background = '#f59e0b';
            badge.style.color = '#78350f';
        }
    }

    // 7. Eventos de Conexão e Auto-Sync
    window.addEventListener('online', () => {
        isOnline = true;
        updateBannerState();
        showNotification('🟢 Conexão restabelecida! Iniciando sincronização automática...', 'success');
        syncPendingData();
    });

    window.addEventListener('offline', () => {
        isOnline = false;
        updateBannerState();
        showNotification('⚠️ Você está offline. O sistema continuará salvando tudo localmente.', 'warning');
    });

    // Inicialização
    document.addEventListener('DOMContentLoaded', () => {
        createConnectivityBanner();
        setupSalesFormInterceptor();
        syncPendingData();

        // Checagem periódica a cada 15 segundos se houver pendências
        setInterval(() => {
            if (navigator.onLine && getPendingSales().length > 0) {
                syncPendingData();
            }
        }, 15000);

        // Disparo de ping e auto-backup a cada 5 minutos
        setInterval(() => {
            if (navigator.onLine) {
                fetch('/api/ping').catch(() => {});
            }
        }, 300000);
    });

})();
