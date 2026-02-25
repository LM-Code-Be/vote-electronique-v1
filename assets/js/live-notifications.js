(() => {
    // Les data-* sont presents dans la navbar portail et la topbar admin.
    const badgeEls = Array.from(document.querySelectorAll('[data-live-notif-badge]'));
    const listEls = Array.from(document.querySelectorAll('[data-live-notif-list]'));
    const emptyEls = Array.from(document.querySelectorAll('[data-live-notif-empty]'));
    const markAllEls = Array.from(document.querySelectorAll('[data-live-notif-mark-all]'));

    if (!badgeEls.length || !listEls.length) return;

    const basePath = String(window.APP_BASE_PATH || '').replace(/\/$/, '');
    const csrfToken = String(window.CSRF_TOKEN || '');
    const apiUrl = `${basePath}/api/ent-my-notifications.php`;

    let knownIds = new Set();
    let firstLoad = true;
    let askPermissionOnce = false;

    const levelClass = (level) => {
        const v = String(level || 'INFO').toUpperCase();
        if (v === 'SUCCESS') return 'text-bg-success';
        if (v === 'WARNING') return 'text-bg-warning';
        if (v === 'ERROR') return 'text-bg-danger';
        return 'text-bg-info';
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatDate = (value) => {
        if (!value) return '';
        const d = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString();
    };

    const toAbsoluteUrl = (target) => {
        const raw = String(target || '').trim();
        if (!raw) return '';
        if (/^https?:\/\//i.test(raw)) return raw;
        // Normalise les slashes et evite le double prefixe /vote/vote.
        const normalize = (value) => value.replace(/([^:]\/)\/+/g, '$1');
        const withLeadingSlash = (value) => value.startsWith('/') ? value : `/${value}`;
        const path = withLeadingSlash(raw);

        if (!basePath) return normalize(path);
        if (path === basePath || path.startsWith(`${basePath}/`)) {
            return normalize(path);
        }
        return normalize(`${basePath}${path}`);
    };

    const showBadge = (count) => {
        const value = Number(count || 0);
        badgeEls.forEach((el) => {
            if (value > 0) {
                el.classList.remove('d-none');
                el.textContent = value > 99 ? '99+' : String(value);
            } else {
                el.classList.add('d-none');
                el.textContent = '0';
            }
        });
    };

    const renderList = (items) => {
        const html = (items || []).map((item) => {
            const id = Number(item?.id || 0);
            const title = escapeHtml(item?.title || 'Notification');
            const body = escapeHtml(item?.body || '');
            const isRead = Number(item?.is_read || 0) === 1;
            const target = toAbsoluteUrl(item?.target_url || '');
            const deliveredAt = formatDate(item?.delivered_at || '');
            const badge = `<span class="badge ${levelClass(item?.level)}">${escapeHtml(item?.level || 'INFO')}</span>`;
            const content = `
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="fw-semibold ${isRead ? 'text-muted' : ''}">${title}</div>
                    ${badge}
                </div>
                ${body ? `<div class="small text-muted mt-1">${body}</div>` : ''}
                <div class="small text-muted mt-1">${escapeHtml(deliveredAt)}</div>
            `;
            if (target) {
                // Si target existe, la notification devient un lien direct cliquable.
                return `<a href="${escapeHtml(target)}" class="list-group-item list-group-item-action live-notif-item ${isRead ? 'opacity-75' : ''}" data-live-notif-item data-id="${id}">${content}</a>`;
            }
            return `<button type="button" class="list-group-item list-group-item-action live-notif-item ${isRead ? 'opacity-75' : ''}" data-live-notif-item data-id="${id}">${content}</button>`;
        }).join('');

        listEls.forEach((el) => {
            el.innerHTML = html;
        });
        emptyEls.forEach((el) => {
            el.classList.toggle('d-none', (items || []).length > 0);
        });
    };

    const requestBrowserPermission = () => {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'default') return;
        if (askPermissionOnce) return;
        askPermissionOnce = true;
        Notification.requestPermission().catch(() => { });
    };

    const showBrowserNotification = (item) => {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;

        const target = toAbsoluteUrl(item?.target_url || '');
        const notif = new Notification(String(item?.title || 'Notification'), {
            body: String(item?.body || ''),
            icon: `${basePath}/assets/brand/lm-code/logo-square.png`,
            tag: `notif-${item?.id || ''}`,
        });
        notif.onclick = () => {
            try {
                window.focus();
            } catch (_) {
                // ignore
            }
            if (target) {
                window.location.href = target;
            }
            notif.close();
        };
    };

    const fetchInbox = async () => {
        const res = await fetch(`${apiUrl}?limit=20`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data?.error) {
            throw new Error(data?.error || `HTTP ${res.status}`);
        }
        return data;
    };

    const postAction = async (payload) => {
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify(payload || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data?.error) {
            throw new Error(data?.error || `HTTP ${res.status}`);
        }
        return data;
    };

    const refresh = async () => {
        const data = await fetchInbox();
        const items = Array.isArray(data?.items) ? data.items : [];
        const ids = new Set(items.map((it) => Number(it?.id || 0)).filter((id) => id > 0));
        const fresh = items.filter((it) => {
            const id = Number(it?.id || 0);
            return id > 0 && !knownIds.has(id);
        });

        renderList(items);
        showBadge(Number(data?.unread_count || 0));

        if (!firstLoad) {
            // On n affiche le push navigateur que pour les nouvelles notifications.
            fresh
                .slice()
                .reverse()
                .forEach((item) => showBrowserNotification(item));
        }

        knownIds = ids;
        firstLoad = false;
    };

    document.addEventListener('click', (event) => {
        const notifTrigger = event.target.closest('[aria-label="Notifications"]');
        if (notifTrigger) {
            requestBrowserPermission();
        }

        const item = event.target.closest('[data-live-notif-item]');
        if (!item) return;
        const id = Number(item.getAttribute('data-id') || '0');
        if (id <= 0) return;
        // Marquage "lu" optimiste a chaque clic.
        postAction({ action: 'READ', id })
            .then(() => refresh().catch(() => { }))
            .catch(() => { });
    });

    markAllEls.forEach((btn) => {
        btn.addEventListener('click', () => {
            postAction({ action: 'READ_ALL' })
                .then(() => refresh().catch(() => { }))
                .catch(() => { });
        });
    });

    refresh().catch(() => { });
    // Polling simple pour rester compatible hebergement mutualise sans websocket.
    setInterval(() => {
        refresh().catch(() => { });
    }, 15000);
})();
