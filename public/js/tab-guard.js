(function () {
    const OWNER_KEY = 'app_tab_owner_id';
    const OWNER_PING_KEY = 'app_tab_owner_ping';
    const MY_TAB_KEY = 'app_my_tab_id';
    const CHANNEL_NAME = 'app_session_channel';

    const PING_INTERVAL_MS = 5000;
    const STALE_TIMEOUT_MS = 15000;

    const channel = new BroadcastChannel(CHANNEL_NAME);

    let myTabId = sessionStorage.getItem(MY_TAB_KEY);
    const esPestanaNueva = !myTabId;

    if (!myTabId) {
        myTabId = (crypto.randomUUID
            ? crypto.randomUUID()
            : Date.now() + '-' + Math.random().toString(36).slice(2));
        sessionStorage.setItem(MY_TAB_KEY, myTabId);
    }

    const ownerActual = localStorage.getItem(OWNER_KEY);
    const ultimoPing = parseInt(localStorage.getItem(OWNER_PING_KEY) || '0', 10);
    const duenoMuerto = ownerActual && (Date.now() - ultimoPing > STALE_TIMEOUT_MS);

    if (duenoMuerto) {
        localStorage.removeItem(OWNER_KEY);
        localStorage.removeItem(OWNER_PING_KEY);
    }

    if (esPestanaNueva) {
        const dueñoAnterior = localStorage.getItem(OWNER_KEY);

        localStorage.setItem(OWNER_KEY, myTabId);
        localStorage.setItem(OWNER_PING_KEY, Date.now().toString());

        channel.postMessage({ type: 'NEW_OWNER', tabId: myTabId });

        if (dueñoAnterior && dueñoAnterior !== myTabId) {
            setTimeout(() => {
                channel.postMessage({ type: 'NEW_OWNER', tabId: myTabId });
            }, 500);
        }
    } else {
        const ownerValido = localStorage.getItem(OWNER_KEY);

        if (!ownerValido) {
            localStorage.setItem(OWNER_KEY, myTabId);
            localStorage.setItem(OWNER_PING_KEY, Date.now().toString());
        } else if (ownerValido === myTabId) {
        } else {
            blockThisTab();
        }
    }

    if (localStorage.getItem(OWNER_KEY) === myTabId) {
        setInterval(() => {
            if (localStorage.getItem(OWNER_KEY) === myTabId) {
                localStorage.setItem(OWNER_PING_KEY, Date.now().toString());
            }
        }, PING_INTERVAL_MS);
    }

    setInterval(() => {
        if (document.getElementById('tab-block-overlay')) {
            const owner = localStorage.getItem(OWNER_KEY);
            const ping = parseInt(localStorage.getItem(OWNER_PING_KEY) || '0', 10);

            if (!owner || (Date.now() - ping > STALE_TIMEOUT_MS)) {
                localStorage.removeItem(OWNER_KEY);
                localStorage.removeItem(OWNER_PING_KEY);
                window.location.reload();
            }
        }
    }, 3000);

    document.addEventListener('DOMContentLoaded', () => {
        const loginForm = document.querySelector(
            'form[action*="login"], form[action*="logear"], #loginForm, form.login-form'
        );
        if (loginForm && !loginForm.querySelector('input[name="tab_id"]')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tab_id';
            input.value = myTabId;
            loginForm.appendChild(input);
        }
    });

    channel.onmessage = (event) => {
        const data = event.data || {};

        if (data.type === 'NEW_OWNER' && data.tabId !== myTabId) {
            blockThisTab();
        }

        if (data.type === 'OWNER_FREED' && data.tabId !== myTabId) {
            if (document.getElementById('tab-block-overlay')) {
                const owner = localStorage.getItem(OWNER_KEY);
                const ping = parseInt(localStorage.getItem(OWNER_PING_KEY) || '0', 10);

                if (!owner || (Date.now() - ping > STALE_TIMEOUT_MS)) {
                    localStorage.setItem(OWNER_KEY, myTabId);
                    localStorage.setItem(OWNER_PING_KEY, Date.now().toString());
                    window.location.reload();
                }
            }
        }
    };

    window.addEventListener('beforeunload', () => {
        const owner = localStorage.getItem(OWNER_KEY);
        if (owner === myTabId) {
            localStorage.removeItem(OWNER_KEY);
            localStorage.removeItem(OWNER_PING_KEY);
            channel.postMessage({ type: 'OWNER_FREED', tabId: myTabId });
        }
    });

    function blockThisTab() {
        if (document.getElementById('tab-block-overlay')) return;

        const div = document.createElement('div');
        div.id = 'tab-block-overlay';
        div.style.cssText = `
            position:fixed; inset:0; background:#111827; color:#fff;
            display:flex; align-items:center; justify-content:center;
            flex-direction:column; gap:1rem; z-index:999999;
            font-family: system-ui, sans-serif; text-align:center; padding:2rem;
        `;
        div.innerHTML = `
            <h2 style="margin:0;font-size:1.5rem;">⚠️ Sesión activa en otra pestaña</h2>
            <p style="margin:0;opacity:.8;max-width:420px;">
                Esta pestaña está bloqueada porque abriste la sesión en otra pestaña
                más reciente de este mismo navegador.
            </p>
            <p style="margin:0;opacity:.5;font-size:0.85rem;">
                Usa la pestaña más reciente para continuar. Si la cierras, esta se desbloqueará.
            </p>
            <button id="tab-block-logout"
                    style="padding:.75rem 1.5rem;border:none;border-radius:8px;
                           background:#e11d48;color:#fff;cursor:pointer;font-size:1rem;">
                Cerrar sesión
            </button>
        `;
        document.body.appendChild(div);

        document.getElementById('tab-block-logout').onclick = () => {
            sessionStorage.removeItem(MY_TAB_KEY);
            window.location.href = '/logout';
        };
    }

    window.__APP_TAB_ID__ = myTabId;
})();