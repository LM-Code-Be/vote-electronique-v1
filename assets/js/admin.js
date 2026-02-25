/*-----------------------------------------------------------
 |  admin.js — Back-office (multi-pages)
 *-----------------------------------------------------------*/
(() => {
    const basePath = (window.APP_BASE_PATH || '').replace(/\/$/, '');
    const apiBase = `${basePath}/api`;
    const apiUrl = (path) => `${apiBase}/${String(path).replace(/^\/+/, '')}`;
    const csrfToken = window.CSRF_TOKEN || '';

    const toast = (msg, type = 'info') => {
        const icon = type === 'success' ? 'check-circle' :
            type === 'error' ? 'x-circle' :
                'info';
        const tpl = `
        <div class="toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-${icon} me-2"></i>${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
        const zone = document.getElementById('toast-zone');
        if (!zone || !window.bootstrap?.Toast) {
            alert(msg);
            return;
        }
        zone.insertAdjacentHTML('beforeend', tpl);
        const el = zone.lastElementChild;
        if (!el) return;
        new bootstrap.Toast(el, { delay: 3000 }).show();
    };

    const fetchJson = async (path, opt = {}) => {
        opt.headers = opt.headers || {};
        if (opt.method && opt.method !== 'GET') {
            opt.headers['X-CSRF-Token'] = csrfToken;
        }
        const res = await fetch(apiUrl(path), opt);
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.error) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }
        return data;
    };

    /* ---------- DASHBOARD --------- */
    if ($('#chart-results').length) {
        let chart;
        const loadDash = async () => {
            const d = await fetchJson('votes?stats=1');
            $('#stat-total-votes').text(d.totalVotes);
            $('#stat-participation').text(`${d.participationRate}%`);
            $('#stat-candidates').text(d.totalCandidates);

            chart?.destroy();
            chart = new Chart(document.getElementById('chart-results'), {
                type: 'bar',
                data: {
                    labels: d.chart.map(e => e.label),
                    datasets: [{ data: d.chart.map(e => e.count) }],
                },
                options: { plugins: { legend: { display: false } } },
            });

            $('#table-recent-votes').html(d.recentVotes.map(r => `
                <tr><td>${new Date(r.voted_at).toLocaleString()}</td><td>${r.first_name} ${r.last_name}</td><td>${r.full_name}</td></tr>`).join(''));
        };

        loadDash().catch(e => toast(e.message, 'error'));
        setInterval(() => loadDash().catch(() => { }), 15000);
    }

    /* ---------- CANDIDATS --------- */
    if ($('#dt-candidates').length) {
        let dtC;
        const listC = async () => {
            const list = await fetchJson('candidates');
            const rows = list.map(c => `
                <tr>
                    <td><img src="${c.photo_path || 'https://via.placeholder.com/60'}" class="rounded" width="60"></td>
                    <td>${c.full_name}</td>
                    <td>${(c.biography || '').slice(0, 100)}</td>
                    <td>${c.vote_count || 0}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger btn-del" data-id="${c.id}"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join('');

            dtC?.destroy();
            $('#dt-candidates tbody').html(rows);
            dtC = $('#dt-candidates').DataTable({ order: [[1, 'asc']] });
        };

        listC().catch(e => toast(e.message, 'error'));

        $('#form-candidate').on('submit', async (e) => {
            e.preventDefault();

            const name = $('#cand-name').val().trim();
            if (!name) return toast('Nom requis', 'error');

            const file = $('#cand-photo')[0].files[0];
            let photoPath = '';

            try {
                if (file) {
                    const fd = new FormData();
                    fd.append('file', file);
                    const res = await fetchJson('upload', { method: 'POST', body: fd });
                    photoPath = res.path || '';
                }

                await fetchJson('candidates', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ full_name: name, biography: $('#cand-bio').val(), photo_path: photoPath }),
                });

                await listC();
                toast('Candidat ajouté', 'success');
                const modalEl = document.getElementById('modal-candidate');
                if (modalEl && window.bootstrap?.Modal) {
                    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
                }
                e.target.reset();
            } catch (err) {
                toast(err.message || String(err), 'error');
            }
        });

        $(document).on('click', '.btn-del', async function () {
            const id = $(this).data('id');
            if (!confirm('Supprimer ?')) return;
            try {
                await fetchJson(`candidates?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                await listC();
                toast('Supprimé', 'success');
            } catch (err) {
                toast(err.message || String(err), 'error');
            }
        });
    }

    /* ---------- ÉLECTEURS --------- */
    if ($('#dt-voters').length) {
        let dtV;
        const listV = async () => {
            const list = await fetchJson('voters');
            const rows = list.map(v => `
                <tr><td>${v.first_name}</td><td>${v.last_name}</td><td>${v.email}</td><td>${v.department}</td><td>${v.has_voted ? 'Oui' : 'Non'}</td></tr>`).join('');
            dtV?.destroy();
            $('#dt-voters tbody').html(rows);
            dtV = $('#dt-voters').DataTable({ order: [[1, 'asc']] });
        };

        listV().catch(e => toast(e.message, 'error'));

        $('#btn-import-voters').on('click', () => $('#file-voters').click());
        $('#file-voters').on('change', async function () {
            const f = this.files[0];
            if (!f) return;
            const fd = new FormData();
            fd.append('file', f);
            try {
                await fetchJson('voters', { method: 'POST', body: fd });
                await listV();
                toast('Import OK', 'success');
            } catch (err) {
                toast(err.message || String(err), 'error');
            } finally {
                this.value = '';
            }
        });
    }

    /* ---------- SETTINGS ---------- */
    if ($('#form-settings').length) {
        $('#form-settings').on('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target).entries());
            data.allow_vote_change = !!data.allow_vote_change;
            try {
                await fetchJson('settings', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                toast('Enregistré', 'success');
            } catch (err) {
                toast(err.message || String(err), 'error');
            }
        });
    }

    /* ---------- LOGS -------------- */
    if ($('#dt-logs').length) {
        fetchJson('logs').then(list => {
            const rows = list.map(l => `
                <tr><td>${new Date(l.created_at).toLocaleString()}</td><td>${l.action_type}</td><td>${l.email || ''}</td><td>${l.details || ''}</td></tr>`).join('');
            $('#dt-logs').DataTable({ data: [], order: [[0, 'desc']] }).destroy();
            $('#dt-logs tbody').html(rows);
            $('#dt-logs').DataTable({ order: [[0, 'desc']] });
        }).catch(e => toast(e.message, 'error'));
    }

    /* ---------- ADMINS ------------ */
    if ($('#dt-admin-users').length) {
        let dtA;
        let cache = [];

        const resetForm = () => {
            $('#admin-id').val('');
            $('#admin-username').val('');
            $('#admin-role').val('admin');
            $('#admin-active').prop('checked', true);
            $('#admin-password').val('');
        };

        const openForCreate = () => {
            resetForm();
            $('#modal-admin-user .modal-title').text('Nouvel admin');
        };

        const openForEdit = (id) => {
            const u = cache.find(x => String(x.id) === String(id));
            if (!u) return toast('Admin introuvable', 'error');
            $('#admin-id').val(u.id);
            $('#admin-username').val(u.username);
            $('#admin-role').val(u.role);
            $('#admin-active').prop('checked', !!u.is_active);
            $('#admin-password').val('');
            $('#modal-admin-user .modal-title').text(`Modifier: ${u.username}`);
            const modalEl = document.getElementById('modal-admin-user');
            if (modalEl && window.bootstrap?.Modal) {
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
            }
        };

        const listA = async () => {
            cache = await fetchJson('admin-users');
            const rows = cache.map(u => `
                <tr>
                    <td>${u.username}</td>
                    <td><span class="badge text-bg-${u.role === 'superadmin' ? 'primary' : 'secondary'}">${u.role}</span></td>
                    <td>${u.is_active ? 'Oui' : 'Non'}</td>
                    <td>${u.created_at ? new Date(u.created_at).toLocaleString() : ''}</td>
                    <td>${u.last_login_at ? new Date(u.last_login_at).toLocaleString() : ''}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary btn-edit-admin" data-id="${u.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-del-admin" data-id="${u.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join('');

            dtA?.destroy();
            $('#dt-admin-users tbody').html(rows);
            dtA = $('#dt-admin-users').DataTable({ order: [[0, 'asc']] });
        };

        openForCreate();
        listA().catch(e => toast(e.message, 'error'));

        $('#modal-admin-user').on('show.bs.modal', () => {
            if (!$('#admin-id').val()) openForCreate();
        });

        $(document).on('click', '.btn-edit-admin', function () {
            openForEdit($(this).data('id'));
        });

        $(document).on('click', '.btn-del-admin', async function () {
            const id = $(this).data('id');
            const u = cache.find(x => String(x.id) === String(id));
            if (!u) return toast('Admin introuvable', 'error');
            if (!confirm(`Supprimer ${u.username} ?`)) return;
            try {
                await fetchJson(`admin-users?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                await listA();
                toast('Admin supprime', 'success');
            } catch (err) {
                toast(err.message || String(err), 'error');
            }
        });

        $('#form-admin-user').on('submit', async (e) => {
            e.preventDefault();
            const id = $('#admin-id').val();
            const payload = {
                username: $('#admin-username').val().trim(),
                role: $('#admin-role').val(),
                is_active: $('#admin-active').is(':checked'),
            };
            const pwd = $('#admin-password').val();
            if (pwd) payload.password = pwd;

            try {
                if (!id) {
                    await fetchJson('admin-users', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Admin cree', 'success');
                } else {
                    await fetchJson(`admin-users?id=${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Admin mis a jour', 'success');
                }

                const modalEl = document.getElementById('modal-admin-user');
                if (modalEl && window.bootstrap?.Modal) {
                    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
                }
                await listA();
                resetForm();
            } catch (err) {
                toast(err.message || String(err), 'error');
            }
        });
    }

    /* ===== ENTERPRISE (enterprise/admin/*) ===== */

    if ($('#ent-stat-elections').length) {
        let votesChart = null;
        const load = async () => {
            const d = await fetchJson('ent-dashboard');
            $('#ent-stat-elections').text(d.stats.elections);
            $('#ent-stat-voters').text(d.stats.voters);
            $('#ent-stat-votes-today').text(d.stats.votesToday);
            $('#ent-stat-open').text(d.stats.open);

            // Chart (optional)
            const ctx = document.getElementById('ent-chart-votes')?.getContext?.('2d');
            if (ctx && window.Chart && Array.isArray(d.votesSeries)) {
                const labels = d.votesSeries.map(x => x.date);
                const values = d.votesSeries.map(x => x.count);
                if (!votesChart) {
                    votesChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Votes',
                                data: values,
                                borderColor: (window.theme?.primary || '#3b7ddd'),
                                backgroundColor: 'rgba(59, 125, 221, 0.15)',
                                fill: true,
                                tension: 0.35,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { ticks: { maxRotation: 0, autoSkip: true } },
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                            },
                        },
                    });
                } else {
                    votesChart.data.labels = labels;
                    votesChart.data.datasets[0].data = values;
                    votesChart.update();
                }
            }

            $('#ent-table-elections').html((d.recentElections || []).map(e => `
                <tr>
                    <td>${e.title}</td>
                    <td><span class="badge text-bg-${e.status === 'PUBLISHED' ? 'success' : e.status === 'CLOSED' ? 'secondary' : 'warning'}">${e.status}</span></td>
                    <td>${new Date(e.start_at).toLocaleString()}</td>
                    <td>${new Date(e.end_at).toLocaleString()}</td>
                </tr>`).join(''));

            $('#ent-table-audit').html((d.recentAudit || []).map(a => `
                <tr>
                    <td>${new Date(a.created_at).toLocaleString()}</td>
                    <td>${a.action}</td>
                    <td>${a.entity_type || ''} ${a.entity_id || ''}</td>
                </tr>`).join(''));
        };
        load().catch(e => toast(e.message, 'error'));
        setInterval(() => load().catch(() => {}), 15000);
    }

    if ($('#dt-ent-roles').length) {
        fetchJson('ent-roles').then(list => {
            const rows = list.map(r => `<tr><td>${r.code}</td><td>${r.label}</td><td>${new Date(r.created_at).toLocaleString()}</td></tr>`).join('');
            $('#dt-ent-roles tbody').html(rows);
            $('#dt-ent-roles').DataTable({ order: [[0, 'asc']] });
        }).catch(e => toast(e.message, 'error'));
    }

    if ($('#dt-ent-groups').length) {
        let dtG;
        let cacheG = [];
        const list = async () => {
            cacheG = await fetchJson('ent-groups');
            const rows = cacheG.map(g => `
                <tr>
                    <td>${g.name}</td>
                    <td>${new Date(g.created_at).toLocaleString()}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary btn-ent-group-edit" data-id="${g.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-ent-group-del" data-id="${g.id}"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join('');
            dtG?.destroy();
            $('#dt-ent-groups tbody').html(rows);
            dtG = $('#dt-ent-groups').DataTable({ order: [[0, 'asc']] });
        };
        list().catch(e => toast(e.message, 'error'));

        const open = (g) => {
            $('#ent-group-id').val(g?.id || '');
            $('#ent-group-name').val(g?.name || '');
            $('#modal-group .modal-title').text(g ? `Modifier: ${g.name}` : 'Nouveau departement');
        };

        $(document).on('click', '.btn-ent-group-edit', function () {
            const id = $(this).data('id');
            const g = cacheG.find(x => String(x.id) === String(id));
            open(g);
            const modalEl = document.getElementById('modal-group');
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        });

        $(document).on('click', '.btn-ent-group-del', async function () {
            const id = $(this).data('id');
            const g = cacheG.find(x => String(x.id) === String(id));
            if (!g) return;
            if (!confirm(`Supprimer ${g.name} ?`)) return;
            try {
                await fetchJson(`ent-groups?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                toast('Supprimé', 'success');
                await list();
            } catch (e) {
                toast(e.message, 'error');
            }
        });

        $('#form-group').on('submit', async (e) => {
            e.preventDefault();
            const id = $('#ent-group-id').val();
            const payload = { name: $('#ent-group-name').val().trim() };
            try {
                if (!id) {
                    await fetchJson('ent-groups', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Cree', 'success');
                } else {
                    await fetchJson(`ent-groups?id=${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Mis a jour', 'success');
                }
                (bootstrap.Modal.getInstance(document.getElementById('modal-group')) || new bootstrap.Modal(document.getElementById('modal-group'))).hide();
                await list();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }

    if ($('#dt-ent-users').length) {
        let dtU;
        let cacheU = [];
        let roles = [];
        let groups = [];
        const canManageRoles = String(document.body?.dataset?.canManageRoles || '') === '1';

        const loadMeta = async () => {
            const [r, g] = await Promise.all([
                canManageRoles
                    ? fetchJson('ent-roles').catch(() => [{ code: 'VOTER' }, { code: 'SCRUTATEUR' }, { code: 'ADMIN' }])
                    : Promise.resolve([{ code: 'VOTER' }, { code: 'SCRUTATEUR' }, { code: 'ADMIN' }, { code: 'SUPERADMIN' }]),
                fetchJson('ent-groups'),
            ]);
            roles = r || [];
            groups = g || [];
            $('#ent-user-roles').html(roles.map(x => `<option value="${x.code}">${x.code}</option>`).join(''));
            $('#ent-user-groups').html(groups.map(x => `<option value="${x.id}">${x.name}</option>`).join(''));
        };

        const list = async () => {
            cacheU = await fetchJson('ent-users');
            const rows = cacheU.map(u => `
                <tr>
                    <td>${u.username}</td>
                    <td>${u.full_name}</td>
                    <td>${u.email || ''}</td>
                    <td>${u.departement || u.service || ''}</td>
                    <td><span class="badge text-bg-${(u.user_type || 'INTERNAL') === 'EXTERNAL' ? 'warning' : 'primary'}">${u.user_type || 'INTERNAL'}</span></td>
                    <td>
                        <span class="badge text-bg-${u.status === 'ACTIVE' ? 'success' : u.status === 'SUSPENDED' ? 'warning' : 'secondary'}">${u.status}</span>
                        ${u.must_reset_password ? '<span class="badge text-bg-warning ms-1">RESET</span>' : ''}
                    </td>
                    <td>${
                        (u.groups || []).length
                            ? (u.groups || []).map(g => `<span class="badge text-bg-light me-1">${g.name || ''}</span>`).join('')
                            : '<span class="text-muted">-</span>'
                    }</td>
                    <td>${(u.roles || []).map(r => `<span class="badge text-bg-${r === 'SUPERADMIN' ? 'primary' : r === 'ADMIN' ? 'info' : r === 'SCRUTATEUR' ? 'secondary' : 'light'} me-1">${r}</span>`).join('')}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary btn-ent-user-edit" data-id="${u.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-secondary btn-ent-user-reset" data-id="${u.id}" title="Réinitialiser"><i class="bi bi-key"></i></button>
                        <button class="btn btn-sm btn-outline-secondary btn-ent-user-revoke" data-id="${u.id}" title="Déconnexion globale"><i class="bi bi-box-arrow-right"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-ent-user-del" data-id="${u.id}"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join('');
            dtU?.destroy();
            $('#dt-ent-users tbody').html(rows);
            dtU = $('#dt-ent-users').DataTable({ order: [[0, 'asc']] });
        };

        const open = (u) => {
            $('#ent-user-id').val(u?.id || '');
            $('#ent-user-username').val(u?.username || '');
            $('#ent-user-email').val(u?.email || '');
            $('#ent-user-employee-id').val(u?.employee_id || '');
            $('#ent-user-full-name').val(u?.full_name || '');
            $('#ent-user-phone').val(u?.phone || '');
            $('#ent-user-departement').val(u?.departement || u?.service || '');
            $('#ent-user-password').val('');
            $('#ent-user-type').val((u?.user_type || 'INTERNAL').toUpperCase());
            $('#ent-user-status').val(u?.status || 'ACTIVE');
            const r = (u?.roles || []);
            $('#ent-user-roles option').prop('selected', false);
            r.forEach(code => $(`#ent-user-roles option[value="${code}"]`).prop('selected', true));
            const g = (u?.groups || []).map(x => String(x.id));
            $('#ent-user-groups option').prop('selected', false);
            g.forEach(id => $(`#ent-user-groups option[value="${id}"]`).prop('selected', true));
            $('#modal-user .modal-title').text(u ? `Modifier: ${u.username}` : 'Nouvel utilisateur');
        };

        loadMeta()
            .then(list)
            .catch(e => toast(e.message, 'error'));

        $(document).on('click', '.btn-ent-user-edit', function () {
            const id = $(this).data('id');
            const u = cacheU.find(x => String(x.id) === String(id));
            if (!u) return;
            open(u);
            const modalEl = document.getElementById('modal-user');
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        });

        $(document).on('click', '.btn-ent-user-del', async function () {
            const id = $(this).data('id');
            const u = cacheU.find(x => String(x.id) === String(id));
            if (!u) return;
            if (!confirm(`Supprimer (soft) ${u.username} ?`)) return;
            try {
                await fetchJson(`ent-users?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                toast('Supprimé', 'success');
                await list();
            } catch (e) {
                toast(e.message, 'error');
            }
        });

        $(document).on('click', '.btn-ent-user-reset', async function () {
            const id = $(this).data('id');
            const u = cacheU.find(x => String(x.id) === String(id));
            if (!u) return;
            if (!confirm(`Générer un lien de réinitialisation pour ${u.username} ?`)) return;
            try {
                const res = await fetchJson(`ent-users?op=reset_password&id=${encodeURIComponent(id)}`, { method: 'POST' });
                const url = res.reset_url || '';
                if (!url) return toast('Lien indisponible', 'error');
                try {
                    await navigator.clipboard.writeText(url);
                    toast('Lien copié dans le presse-papiers', 'success');
                } catch (_) {
                    // ignore
                }
                window.prompt('Lien de réinitialisation (copie/colle) :', url);
                await list();
            } catch (e) {
                toast(e.message, 'error');
            }
        });

        $(document).on('click', '.btn-ent-user-revoke', async function () {
            const id = $(this).data('id');
            const u = cacheU.find(x => String(x.id) === String(id));
            if (!u) return;
            if (!confirm(`Déconnecter toutes les sessions de ${u.username} ?`)) return;
            try {
                const res = await fetchJson(`ent-users?op=revoke_sessions&id=${encodeURIComponent(id)}`, { method: 'POST' });
                toast(`Sessions révoquées: ${res.revoked ?? 0}`, 'success');
                await list();
            } catch (e) {
                toast(e.message, 'error');
            }
        });

        $('#form-user').on('submit', async (e) => {
            e.preventDefault();
            const id = $('#ent-user-id').val();
            const payload = {
                username: $('#ent-user-username').val().trim(),
                email: $('#ent-user-email').val().trim(),
                employee_id: $('#ent-user-employee-id').val().trim(),
                full_name: $('#ent-user-full-name').val().trim(),
                phone: $('#ent-user-phone').val().trim(),
                departement: $('#ent-user-departement').val().trim(),
                user_type: $('#ent-user-type').val(),
                status: $('#ent-user-status').val(),
                groups: $('#ent-user-groups').val() || [],
            };
            if (canManageRoles) {
                payload.roles = $('#ent-user-roles').val() || [];
            }
            const pwd = $('#ent-user-password').val();
            if (pwd) payload.password = pwd;
            try {
                if (!id) {
                    await fetchJson('ent-users', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Cree', 'success');
                } else {
                    await fetchJson(`ent-users?id=${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Mis a jour', 'success');
                }
                (bootstrap.Modal.getInstance(document.getElementById('modal-user')) || new bootstrap.Modal(document.getElementById('modal-user'))).hide();
                await list();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
        $('#ent-btn-import-users').on('click', () => $('#ent-file-users').click());
        $('#ent-btn-export-users').on('click', () => {
            window.location = `${apiBase}/ent-users?format=csv`;
        });
        $('#ent-file-users').on('change', async function () {
            const f = this.files[0];
            if (!f) return;
            const fd = new FormData();
            fd.append('file', f);
            try {
                const res = await fetchJson('ent-users', { method: 'POST', body: fd });
                toast(`Import OK (imported=${res.imported} updated=${res.updated} skipped=${res.skipped})`, 'success');
                await list();
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                this.value = '';
            }
        });
    }

    if ($('#dt-ent-voter-roll').length) {
        let dtVR;
        let electionId = null;
        let cacheVR = [];
        let electionsVR = [];
        const currentElectionVR = () => electionsVR.find(e => String(e.id) === String(electionId)) || null;
        const canEditCurrentVR = () => !!(currentElectionVR()?.can_edit);

        const applyVoterRollPermissions = () => {
            const canEdit = canEditCurrentVR();
            $('#ent-vr-clear').prop('disabled', !canEdit);
            $('#ent-vr-generate').prop('disabled', !canEdit);
            $('.ent-vr-toggle').prop('disabled', !canEdit);
        };

        const loadElections = async () => {
            electionsVR = await fetchJson('ent-elections');
            const opts = (electionsVR || []).map(e => `<option value="${e.id}">${e.id} • ${e.title} (${e.status})</option>`).join('');
            $('#ent-vr-election').html(opts);
            electionId = $('#ent-vr-election').val() || null;
            applyVoterRollPermissions();
        };

        const renderBanner = (hasSnapshot) => {
            const el = document.getElementById('ent-vr-banner');
            const txt = document.getElementById('ent-vr-banner-text');
            if (!el || !txt) return;
            el.style.display = 'flex';
            txt.textContent = hasSnapshot
                ? 'Snapshot actif : l’éligibilité est figée pour ce scrutin (modifiable ici).'
                : 'Aucun snapshot : l’éligibilité est calculée dynamiquement (groupes + statut).';
        };

        const list = async () => {
            if (!electionId) return;
            const res = await fetchJson(`ent-voter-roll?election_id=${encodeURIComponent(electionId)}`);
            cacheVR = res.rows || [];
            renderBanner(!!res.has_snapshot);

            const rows = cacheVR.map(u => `
                <tr>
                    <td>${u.username}</td>
                    <td>${u.full_name}</td>
                    <td>${u.email || ''}</td>
                    <td>${u.departement || u.service || ''}</td>
                    <td><span class="badge text-bg-${u.status === 'ACTIVE' ? 'success' : u.status === 'SUSPENDED' ? 'warning' : 'secondary'}">${u.status}</span></td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-flex align-items-center m-0">
                            <input class="form-check-input ent-vr-toggle" type="checkbox" data-user-id="${u.id}" ${u.eligible ? 'checked' : ''}>
                        </div>
                    </td>
                </tr>`).join('');

            dtVR?.destroy();
            $('#dt-ent-voter-roll tbody').html(rows);
            dtVR = $('#dt-ent-voter-roll').DataTable({ order: [[0, 'asc']] });
            applyVoterRollPermissions();
        };

        loadElections()
            .then(list)
            .catch(e => toast(e.message, 'error'));

        $('#ent-vr-election').on('change', async function () {
            electionId = $(this).val() || null;
            applyVoterRollPermissions();
            await list();
        });

        $('#ent-vr-refresh').on('click', async () => {
            await list();
        });

        $('#ent-vr-export').on('click', () => {
            if (!electionId) return;
            window.location = `${apiBase}/ent-voter-roll?election_id=${encodeURIComponent(electionId)}&format=csv`;
        });

        $('#ent-vr-clear').on('click', async () => {
            if (!electionId) return;
            if (!canEditCurrentVR()) return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            if (!confirm('Effacer le snapshot ? (l’éligibilité redeviendra dynamique)')) return;
            try {
                const res = await fetchJson(`ent-voter-roll?election_id=${encodeURIComponent(electionId)}&op=clear`, { method: 'POST' });
                toast(`Snapshot effacé (${res.deleted ?? 0})`, 'success');
                await list();
            } catch (e) {
                toast(e.message, 'error');
            }
        });

        $('#ent-vr-generate').on('click', async () => {
            if (!electionId) return;
            if (!canEditCurrentVR()) return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            if (!confirm('Générer le snapshot ? (fige l’éligibilité actuelle)')) return;
            try {
                const res = await fetchJson(`ent-voter-roll?election_id=${encodeURIComponent(electionId)}&op=generate`, { method: 'POST' });
                toast(`Snapshot généré (inserted=${res.inserted ?? 0})`, 'success');
                await list();
            } catch (e) {
                toast(e.message, 'error');
            }
        });

        $(document).on('change', '.ent-vr-toggle', async function () {
            if (!electionId) return;
            if (!canEditCurrentVR()) {
                await list();
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            const userId = $(this).data('user-id');
            const eligible = $(this).is(':checked');
            try {
                await fetchJson(`ent-voter-roll?election_id=${encodeURIComponent(electionId)}&user_id=${encodeURIComponent(userId)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ eligible: eligible ? 1 : 0 }),
                });
                toast('Mis à jour', 'success');
            } catch (e) {
                toast(e.message, 'error');
                await list();
            }
        });
    }

    if ($('#dt-ent-participation').length) {
        let dtP;
        let electionId = null;
        let cacheP = [];
        let pollTimer = null;
        let lastVotedCount = null;

        const loadElections = async () => {
            const elections = await fetchJson('ent-elections');
            const opts = (elections || []).map(e => `<option value="${e.id}">${e.id} • ${e.title} (${e.status})</option>`).join('');
            $('#ent-part-election').html(opts);
            electionId = $('#ent-part-election').val() || null;
        };

        const updateRecentVotes = (recentVotes) => {
            const items = Array.isArray(recentVotes) ? recentVotes : [];
            if (!items.length) {
                $('#ent-part-recent').html('Aucun vote enregistre pour le moment.');
                return;
            }
            const html = items.map((row) => {
                const who = row.full_name || row.username || `#${row.user_id || ''}`;
                return `<div><span class="fw-semibold">${who}</span> <span class="text-muted">(${row.departement || 'N/A'})</span> <span class="badge text-bg-success ms-1">${row.voted_at || ''}</span></div>`;
            }).join('');
            $('#ent-part-recent').html(html);
        };

        const setSyncMeta = (generatedAt, votedCount) => {
            const now = generatedAt ? new Date(generatedAt) : new Date();
            $('#ent-part-last-sync').text(`Derniere mise a jour: ${now.toLocaleString()}`);
            if (lastVotedCount !== null && Number(votedCount) > Number(lastVotedCount)) {
                toast(`Nouveaux votes detectes (+${Number(votedCount) - Number(lastVotedCount)})`, 'success');
            }
            lastVotedCount = Number(votedCount || 0);
        };

        const setLiveStatus = () => {
            const enabled = $('#ent-part-live').is(':checked');
            const every = parseInt($('#ent-part-interval').val(), 10) || 10;
            $('#ent-part-live-status').text(enabled ? `Live actif (toutes les ${every}s)` : 'Live desactive');
        };

        const schedulePolling = () => {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
            setLiveStatus();
            if (!$('#ent-part-live').is(':checked')) return;
            const every = Math.max(5, parseInt($('#ent-part-interval').val(), 10) || 10);
            pollTimer = setInterval(() => {
                list(true).catch(() => { });
            }, every * 1000);
        };

        const list = async (silent = false) => {
            if (!electionId) return;
            const onlyMissing = $('#ent-part-missing').is(':checked') ? 1 : 0;
            const res = await fetchJson(`ent-participation?election_id=${encodeURIComponent(electionId)}&only_missing=${onlyMissing}`);
            cacheP = res.rows || [];

            $('#ent-part-eligible').text(res.eligible ?? cacheP.length);
            $('#ent-part-voted').text(res.voted ?? '-');
            $('#ent-part-rate').text(`${res.rate ?? 0}%`);
            setSyncMeta(res.generated_at || null, res.voted ?? 0);
            updateRecentVotes(res.recent_votes || []);

            const rows = cacheP.map(u => `
                <tr>
                    <td>${u.username}</td>
                    <td>${u.full_name}</td>
                    <td>${u.email || ''}</td>
                    <td>${u.departement || u.service || ''}</td>
                    <td>${u.groups || ''}</td>
                    <td>${u.voted_at ? `<span class="badge text-bg-success">${u.voted_at}</span>` : '<span class="badge text-bg-secondary">Non</span>'}</td>
                </tr>`).join('');

            dtP?.destroy();
            $('#dt-ent-participation tbody').html(rows);
            dtP = $('#dt-ent-participation').DataTable({ order: [[0, 'asc']] });

            if (!silent) {
                toast('Participation rafraichie', 'info');
            }
        };

        loadElections()
            .then(() => list(true))
            .catch(e => toast(e.message, 'error'));
        schedulePolling();

        $('#ent-part-election').on('change', async function () {
            electionId = $(this).val() || null;
            await list(true);
        });
        $('#ent-part-missing').on('change', () => list(true).catch(e => toast(e.message, 'error')));
        $('#ent-part-refresh').on('click', () => list(false).catch(e => toast(e.message, 'error')));
        $('#ent-part-live, #ent-part-interval').on('change', () => {
            schedulePolling();
            list(true).catch(e => toast(e.message, 'error'));
        });
        $('#ent-part-export').on('click', () => {
            if (!electionId) return;
            const onlyMissing = $('#ent-part-missing').is(':checked') ? 1 : 0;
            window.location = `${apiBase}/ent-participation?election_id=${encodeURIComponent(electionId)}&only_missing=${onlyMissing}&format=csv`;
        });

        window.addEventListener('beforeunload', () => {
            if (pollTimer) clearInterval(pollTimer);
        });
    }

    if ($('#dt-ent-elections').length) {
        let dtE;
        let cacheE = [];
        let groups = [];
        let candidateUsers = [];
        let organizerUsers = [];
        let wizStep = 1;
        let currentEditingElection = null;
        const canManageElectionsGlobal = String(document.body?.dataset?.canManageElections || '') === '1';

        const loadGroups = async () => {
            groups = await fetchJson('ent-groups');
            $('#ent-election-groups').html(groups.map(g => `<option value="${g.id}">${g.name}</option>`).join(''));
        };

        const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const normalizeAudience = (v) => String(v || 'INTERNAL').toUpperCase();
        const normalizeUserType = (v) => String(v || 'INTERNAL').toUpperCase();
        const userTypeAllowedForAudience = (audienceMode, userType) => {
            const aud = normalizeAudience(audienceMode);
            const type = normalizeUserType(userType);
            if (aud === 'INTERNAL') return type === 'INTERNAL';
            if (aud === 'EXTERNAL') return type === 'EXTERNAL';
            return type === 'INTERNAL' || type === 'EXTERNAL';
        };

        const loadCandidateUsers = async () => {
            let users = [];
            try {
                users = await fetchJson('ent-users?scope=candidates');
            } catch (_) {
                users = [];
            }
            if (!Array.isArray(users) || users.length === 0) {
                const allUsers = await fetchJson('ent-users');
                users = (Array.isArray(allUsers) ? allUsers : []).filter(u => String(u?.status || '').toUpperCase() === 'ACTIVE');
            }
            candidateUsers = users;
            const options = (candidateUsers || []).map(u => {
                const label = u.label || u.full_name || u.username || `#${u.id}`;
                return `<option value="${u.id}">${escapeHtml(label)} (${escapeHtml(u.user_type || 'INTERNAL')})</option>`;
            }).join('');
            $('#ent-election-candidate-users').html(options || '<option value="" disabled>Aucun utilisateur actif</option>');
        };

        const loadOrganizerUsers = async () => {
            let users = [];
            try {
                users = await fetchJson('ent-users?scope=organizers');
            } catch (_) {
                users = [];
            }
            if (!Array.isArray(users) || users.length === 0) {
                try {
                    users = await fetchJson('ent-users?scope=candidates');
                } catch (_) {
                    users = [];
                }
            }
            organizerUsers = Array.isArray(users) ? users : [];
            const opts = organizerUsers.map((u) => {
                const label = u.label || u.full_name || u.username || `#${u.id}`;
                const roleSuffix = String(u.role_codes || '').trim();
                const display = roleSuffix ? `${label} [${roleSuffix}]` : label;
                return `<option value="${escapeHtml(display)}">${escapeHtml(display)}</option>`;
            }).join('');
            $('#ent-election-organizer').html('<option value="">Selectionner un organisateur</option>' + opts);
        };

        const typeNeedsCandidates = (type) => ['SINGLE', 'MULTI', 'RANKED'].includes(String(type || '').toUpperCase());
        const applyAudienceToCandidatesPicker = () => {
            const audienceMode = normalizeAudience($('#ent-election-audience').val());
            let removedCount = 0;

            $('#ent-election-candidate-users option').each(function () {
                const id = parseInt($(this).val(), 10);
                if (!Number.isInteger(id) || id <= 0) return;
                const user = candidateUsers.find(x => String(x.id) === String(id));
                const allowed = userTypeAllowedForAudience(audienceMode, user?.user_type || 'INTERNAL');
                $(this).prop('disabled', !allowed);
                this.hidden = !allowed;
                if (!allowed && $(this).prop('selected')) {
                    $(this).prop('selected', false);
                    removedCount += 1;
                }
            });

            const needs = typeNeedsCandidates($('#ent-election-type').val());
            $('#ent-election-candidates-wrap').toggleClass('d-none', !needs);
            if (!needs) {
                $('#ent-election-candidate-users option').prop('selected', false);
            }

            $('#ent-cands-select-all-internal').prop('disabled', audienceMode === 'EXTERNAL' || !needs);
            $('#ent-cands-select-all-external').prop('disabled', audienceMode === 'INTERNAL' || !needs);
            $('#ent-cands-clear').prop('disabled', !needs);

            if (removedCount > 0) {
                toast('Des candidats hors audience ont ete retires automatiquement.', 'info');
            }
        };

        const selectCandidateUsersByType = (userType) => {
            const wanted = String(userType || '').toUpperCase();
            const audienceMode = normalizeAudience($('#ent-election-audience').val());
            $('#ent-election-candidate-users option').each(function () {
                const id = parseInt($(this).val(), 10);
                if (!Number.isInteger(id) || id <= 0) {
                    $(this).prop('selected', false);
                    return;
                }
                const u = candidateUsers.find(x => String(x.id) === String(id));
                const userTypeCur = String(u?.user_type || 'INTERNAL').toUpperCase();
                const isMatch = userTypeCur === wanted && userTypeAllowedForAudience(audienceMode, userTypeCur);
                $(this).prop('selected', isMatch);
            });
            $('#ent-election-candidate-users').trigger('change');
        };

        const collectPayload = () => {
            const payload = {
                title: $('#ent-election-title').val().trim(),
                organizer: $('#ent-election-organizer').val().trim(),
                description: $('#ent-election-description').val(),
                type: $('#ent-election-type').val(),
                audience_mode: $('#ent-election-audience').val(),
                results_visibility: $('#ent-election-results-vis').val(),
                timezone: $('#ent-election-timezone').val().trim(),
                max_choices: $('#ent-election-max-choices').val(),
                start_at: $('#ent-election-start').val(),
                end_at: $('#ent-election-end').val(),
                is_anonymous: $('#ent-election-anon').is(':checked'),
                is_mandatory: $('#ent-election-mandatory').is(':checked'),
                allow_vote_change: $('#ent-election-allow-change').is(':checked'),
                display_order_mode: $('#ent-election-random-order').is(':checked') ? 'RANDOM' : 'MANUAL',
                group_ids: $('#ent-election-groups').val() || [],
                candidate_user_ids: ($('#ent-election-candidate-users').val() || []).map(v => parseInt(v, 10)).filter(v => Number.isInteger(v) && v > 0),
            };

            if (currentEditingElection && currentEditingElection.id) {
                const status = String(currentEditingElection.status || 'DRAFT').toUpperCase();
                if (status !== 'DRAFT') {
                    payload.type = currentEditingElection.type || payload.type;
                    payload.audience_mode = currentEditingElection.audience_mode || payload.audience_mode;
                    payload.results_visibility = currentEditingElection.results_visibility || payload.results_visibility;
                    payload.timezone = currentEditingElection.timezone || payload.timezone;
                    payload.start_at = currentEditingElection.start_at || payload.start_at;
                    payload.is_anonymous = !!currentEditingElection.is_anonymous;
                    payload.is_mandatory = !!currentEditingElection.is_mandatory;
                    payload.allow_vote_change = !!currentEditingElection.allow_vote_change;
                    payload.max_choices = currentEditingElection.max_choices ?? payload.max_choices;
                    payload.display_order_mode = currentEditingElection.display_order_mode || payload.display_order_mode;
                    payload.group_ids = Array.isArray(currentEditingElection.group_ids) ? currentEditingElection.group_ids : [];
                    if (status !== 'PUBLISHED') {
                        payload.end_at = currentEditingElection.end_at || payload.end_at;
                    }
                }
            }

            return payload;
        };

        const wizShow = (step) => {
            wizStep = Math.max(1, Math.min(4, step));
            document.querySelectorAll('.wizard-steps .nav-link').forEach(btn => {
                const s = parseInt(btn.getAttribute('data-step') || '0', 10);
                btn.classList.toggle('active', s === wizStep);
            });
            document.querySelectorAll('.ent-wizard-step').forEach(el => {
                const s = parseInt(el.getAttribute('data-step') || '0', 10);
                el.classList.toggle('d-none', s !== wizStep);
            });

            const prev = document.getElementById('ent-wiz-prev');
            const next = document.getElementById('ent-wiz-next');
            const submit = document.getElementById('ent-wiz-submit');
            if (prev) prev.disabled = wizStep <= 1;
            if (next) next.classList.toggle('d-none', wizStep >= 4);
            if (submit) submit.classList.toggle('d-none', wizStep < 4);

            if (wizStep === 4) {
                const payload = collectPayload();
                const groupsSel = (payload.group_ids || []).map(id => groups.find(g => String(g.id) === String(id))?.name || `#${id}`);
                const candidateSel = (payload.candidate_user_ids || []).map(id => {
                    const u = candidateUsers.find(x => String(x.id) === String(id));
                    return u?.label || u?.full_name || u?.username || `#${id}`;
                });
                const summary = document.getElementById('ent-election-summary');
                if (summary) {
                    summary.innerHTML = `
                        <div class="row g-2">
                            <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Titre</div><div class="fw-semibold">${escapeHtml(payload.title || '')}</div></div></div>
                            <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Type</div><div class="fw-semibold">${escapeHtml(payload.type || '')} ${payload.is_anonymous ? '• Anonyme' : '• Nominatif'}</div></div></div>
                            <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Audience</div><div class="fw-semibold">${escapeHtml(payload.audience_mode || 'INTERNAL')}</div></div></div>
                            <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Résultats votants</div><div class="fw-semibold">Après clôture uniquement</div></div></div>
                            <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Début</div><div class="fw-semibold">${escapeHtml(payload.start_at || '')}</div></div></div>
                            <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Fin</div><div class="fw-semibold">${escapeHtml(payload.end_at || '')}</div></div></div>
                            <div class="col-12"><div class="p-3 bg-light rounded"><div class="text-muted small">Groupes</div><div class="fw-semibold">${groupsSel.length ? escapeHtml(groupsSel.join(', ')) : 'Tous les votants actifs de l audience selectionnee'}</div></div></div>
                            <div class="col-12"><div class="p-3 bg-light rounded"><div class="text-muted small">Candidats initiaux</div><div class="fw-semibold">${candidateSel.length ? escapeHtml(candidateSel.join(', ')) : 'Aucun selectionne'}</div></div></div>
                        </div>
                    `;
                }
            }
        };

        const wizValidateForStep = (step) => {
            const payload = collectPayload();
            if (step >= 1) {
                if (!payload.title) throw new Error('Titre requis');
            }
            if (step >= 2) {
                if (!payload.type) throw new Error('Type requis');
                if (!payload.audience_mode) throw new Error('Audience requise');
                if (!payload.results_visibility) throw new Error('Visibilité des résultats requise');
                if (!payload.timezone) throw new Error('Fuseau horaire requis');
                if (!payload.start_at || !payload.end_at) throw new Error('Dates requises');
                if (typeNeedsCandidates(payload.type) && payload.audience_mode !== 'EXTERNAL' && (!payload.candidate_user_ids || payload.candidate_user_ids.length === 0)) {
                    throw new Error('Ajoute au moins un candidat utilisateur');
                }
            }
        };

        const list = async () => {
            cacheE = await fetchJson('ent-elections');
            const rows = cacheE.map(e => `
                <tr>
                    <td>${e.title}</td>
                    <td>${e.type}</td>
                    <td><span class="badge text-bg-${(e.audience_mode || 'INTERNAL') === 'EXTERNAL' ? 'warning' : (e.audience_mode || 'INTERNAL') === 'HYBRID' ? 'info' : 'primary'}">${e.audience_mode || 'INTERNAL'}</span></td>
                    <td>${e.is_anonymous ? 'Oui' : 'Non'}</td>
                    <td><span class="badge text-bg-${e.status === 'PUBLISHED' ? 'success' : e.status === 'CLOSED' ? 'secondary' : e.status === 'ARCHIVED' ? 'dark' : 'warning'}">${e.status}</span></td>
                    <td>${new Date(e.start_at).toLocaleString()}</td>
                    <td>${new Date(e.end_at).toLocaleString()}</td>
                    <td class="text-center">
                        <a class="btn btn-sm btn-outline-secondary ${e.can_manage_candidates ? '' : 'disabled'}" href="${e.can_manage_candidates ? `${basePath}/enterprise/admin/candidates.php?election_id=${encodeURIComponent(e.id)}` : '#'}" title="${e.can_manage_candidates ? 'Candidats' : 'Reserve au createur / SUPERADMIN'}" ${e.can_manage_candidates ? '' : 'aria-disabled="true"'}><i class="bi bi-people"></i></a>
                        <a class="btn btn-sm btn-outline-secondary" href="${basePath}/enterprise/preview.php?id=${encodeURIComponent(e.id)}" target="_blank" rel="noopener" title="Aperçu"><i class="bi bi-eye"></i></a>
                        <button class="btn btn-sm ${e.can_edit ? 'btn-outline-primary' : 'btn-outline-secondary'} btn-ent-election-edit" data-id="${e.id}" title="${e.can_edit ? 'Modifier' : 'Reserve au createur / SUPERADMIN'}" ${e.can_edit ? '' : 'disabled'}><i class="bi bi-pencil"></i></button>
                        <div class="btn-group">
                            <button class="btn btn-sm ${(e.status === 'PUBLISHED') ? 'btn-success' : (e.can_publish ? 'btn-outline-success' : 'btn-outline-secondary')} btn-ent-election-publish" data-id="${e.id}" title="${(e.status === 'PUBLISHED') ? 'Publiee' : (e.can_publish ? 'Publier' : 'Reserve au createur / SUPERADMIN')}" ${((e.status === 'PUBLISHED') || !e.can_publish) ? 'disabled' : ''}><i class="bi bi-broadcast"></i></button>
                            <button class="btn btn-sm btn-outline-secondary btn-ent-election-close" data-id="${e.id}" title="${e.can_close ? 'Cloturer' : 'Reserve au createur / SUPERADMIN'}" ${e.can_close ? '' : 'disabled'}><i class="bi bi-lock"></i></button>
                            <button class="btn btn-sm btn-outline-danger btn-ent-election-del" data-id="${e.id}" title="${e.can_delete ? 'Archiver' : 'Reserve au createur / SUPERADMIN'}" ${e.can_delete ? '' : 'disabled'}><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>`).join('');
            dtE?.destroy();
            $('#dt-ent-elections tbody').html(rows);
            dtE = $('#dt-ent-elections').DataTable({ order: [[5, 'desc']] });
            const typeVal = $('#ent-filter-type').val();
            const audienceVal = $('#ent-filter-audience').val();
            const statusVal = $('#ent-filter-status').val();
            dtE.column(1).search(typeVal ? String(typeVal) : '', true, false);
            dtE.column(2).search(audienceVal ? String(audienceVal) : '', true, false);
            dtE.column(4).search(statusVal ? String(statusVal) : '', true, false).draw();
        };

        const open = (e) => {
            currentEditingElection = e || null;
            $('#ent-election-id').val(e?.id || '');
            $('#ent-election-title').val(e?.title || '');
            const organizerValue = String(e?.organizer || '').trim();
            const organizerExists = $('#ent-election-organizer option').filter(function () {
                return String($(this).val() || '') === organizerValue;
            }).length > 0;
            if (organizerValue !== '' && !organizerExists) {
                $('#ent-election-organizer').append(`<option value="${escapeHtml(organizerValue)}">${escapeHtml(organizerValue)}</option>`);
            }
            $('#ent-election-organizer').val(organizerValue);
            $('#ent-election-description').val(e?.description || '');
            $('#ent-election-type').val(e?.type || 'SINGLE');
            $('#ent-election-audience').val(e?.audience_mode || 'INTERNAL');
            $('#ent-election-results-vis').val(e?.results_visibility || 'AFTER_CLOSE');
            $('#ent-election-timezone').val(e?.timezone || 'Europe/Paris');
            $('#ent-election-max-choices').val(e?.max_choices || '');
            $('#ent-election-start').val((e?.start_at || '').replace(' ', 'T'));
            $('#ent-election-end').val((e?.end_at || '').replace(' ', 'T'));
            $('#ent-election-anon').prop('checked', !!e?.is_anonymous);
            $('#ent-election-mandatory').prop('checked', !!e?.is_mandatory);
            $('#ent-election-allow-change').prop('checked', !!e?.allow_vote_change);
            $('#ent-election-random-order').prop('checked', (e?.display_order_mode || 'MANUAL') === 'RANDOM');
            $('#ent-election-groups option').prop('selected', false);
            (e?.group_ids || []).forEach(id => $(`#ent-election-groups option[value="${id}"]`).prop('selected', true));
            $('#ent-election-candidate-users option').prop('selected', false);
            (e?.candidate_user_ids || []).forEach(id => $(`#ent-election-candidate-users option[value="${id}"]`).prop('selected', true));
            $('#modal-election .modal-title').text(e ? `Modifier: ${e.title}` : 'Nouvelle election');

            const status = e?.status || 'DRAFT';
            const locked = e && status !== 'DRAFT';
            const allowEnd = status === 'PUBLISHED';
            $('#ent-election-type').prop('disabled', !!locked);
            $('#ent-election-audience').prop('disabled', !!locked);
            $('#ent-election-results-vis').prop('disabled', !!locked);
            $('#ent-election-timezone').prop('disabled', !!locked);
            $('#ent-election-start').prop('disabled', !!locked);
            $('#ent-election-anon').prop('disabled', !!locked);
            $('#ent-election-mandatory').prop('disabled', !!locked);
            $('#ent-election-allow-change').prop('disabled', !!locked);
            $('#ent-election-max-choices').prop('disabled', !!locked);
            $('#ent-election-random-order').prop('disabled', !!locked);
            $('#ent-election-groups').prop('disabled', !!locked);
            $('#ent-election-end').prop('disabled', e ? !allowEnd && locked : false);
            $('#ent-election-candidate-users').prop('disabled', false);
            applyAudienceToCandidatesPicker();

            wizShow(1);
        };

        const initElections = canManageElectionsGlobal
            ? Promise.all([loadGroups(), loadCandidateUsers(), loadOrganizerUsers()])
            : Promise.resolve();
        initElections.then(list).catch(e => toast(e.message, 'error'));

        // Status filter (DataTables)
        $('#ent-filter-status').on('change', function () {
            const v = $(this).val();
            if (!dtE) return;
            dtE.column(4).search(v ? String(v) : '', true, false).draw();
        });
        $('#ent-filter-type').on('change', function () {
            const v = $(this).val();
            if (!dtE) return;
            dtE.column(1).search(v ? String(v) : '', true, false).draw();
        });
        $('#ent-filter-audience').on('change', function () {
            const v = $(this).val();
            if (!dtE) return;
            dtE.column(2).search(v ? String(v) : '', true, false).draw();
        });
        $('#ent-election-audience').on('change', applyAudienceToCandidatesPicker);
        $('#ent-election-type').on('change', applyAudienceToCandidatesPicker);
        $('#ent-cands-select-all-internal').on('click', () => selectCandidateUsersByType('INTERNAL'));
        $('#ent-cands-select-all-external').on('click', () => selectCandidateUsersByType('EXTERNAL'));
        $('#ent-cands-clear').on('click', () => {
            $('#ent-election-candidate-users option').prop('selected', false);
            $('#ent-election-candidate-users').trigger('change');
        });

        // Wizard navigation
        $(document).on('click', '.wizard-steps .nav-link', function () {
            const step = parseInt($(this).attr('data-step') || '1', 10);
            wizShow(step);
        });
        $('#ent-wiz-prev').on('click', () => wizShow(wizStep - 1));
        $('#ent-wiz-next').on('click', () => {
            try {
                wizValidateForStep(wizStep);
                wizShow(wizStep + 1);
            } catch (err) {
                toast(err.message || 'Erreur', 'error');
            }
        });

        // Default modal behavior for "create"
        $('#modal-election').on('show.bs.modal', (ev) => {
            if (!canManageElectionsGlobal) {
                toast('Mode lecture seule: creation non autorisee.', 'error');
                ev.preventDefault();
                return;
            }
            const id = $('#ent-election-id').val();
            if (!id) open(null);
            applyAudienceToCandidatesPicker();
        });
        $('#modal-election').on('hidden.bs.modal', () => {
            $('#ent-election-id').val('');
            wizShow(1);
        });

        $(document).on('click', '.btn-ent-election-edit', function () {
            const id = $(this).data('id');
            const e = cacheE.find(x => String(x.id) === String(id));
            if (!e) return;
            if (!e.can_edit) {
                toast('Seul le createur du scrutin ou un SUPERADMIN peut modifier.', 'error');
                return;
            }
            open(e);
            const modalEl = document.getElementById('modal-election');
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        });

        $(document).on('click', '.btn-ent-election-publish', async function () {
            const id = $(this).data('id');
            try {
                await fetchJson(`ent-elections?id=${encodeURIComponent(id)}&action=publish`, { method: 'POST' });
                toast('Publiée', 'success');
                await list();
            } catch (e) { toast(e.message, 'error'); }
        });

        $(document).on('click', '.btn-ent-election-close', async function () {
            const id = $(this).data('id');
            if (!confirm('Clôturer ?')) return;
            try {
                await fetchJson(`ent-elections?id=${encodeURIComponent(id)}&action=close`, { method: 'POST' });
                toast('Clôturée', 'success');
                await list();
            } catch (e) { toast(e.message, 'error'); }
        });

        $(document).on('click', '.btn-ent-election-del', async function () {
            const id = $(this).data('id');
            if (!confirm('Archiver ce scrutin ?')) return;
            try {
                await fetchJson(`ent-elections?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                toast('Scrutin archive', 'success');
                await list();
            } catch (e) { toast(e.message, 'error'); }
        });

        $('#form-election').on('submit', async (e) => {
            e.preventDefault();
            const id = $('#ent-election-id').val();
            const payload = collectPayload();
            try {
                wizValidateForStep(2);
                wizShow(4);
                if (!id) {
                    payload.candidates = (payload.candidate_user_ids || []).map((uid, index) => ({
                        user_id: uid,
                        display_order: index + 1,
                        is_validated: true,
                        is_active: true,
                    }));
                    await fetchJson('ent-elections', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Election creee', 'success');
                } else {
                    const current = cacheE.find(x => String(x.id) === String(id));
                    if (current && !current.can_edit) {
                        throw new Error('Seul le createur du scrutin ou un SUPERADMIN peut modifier.');
                    }
                    const selectedCandidateUserIds = Array.isArray(payload.candidate_user_ids) ? payload.candidate_user_ids : [];
                    delete payload.candidate_user_ids;
                    await fetchJson(`ent-elections?id=${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    await fetchJson(`ent-elections?id=${encodeURIComponent(id)}&action=sync_candidates`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ candidate_user_ids: selectedCandidateUserIds }),
                    });
                    toast('Mise a jour', 'success');
                }
                (bootstrap.Modal.getInstance(document.getElementById('modal-election')) || new bootstrap.Modal(document.getElementById('modal-election'))).hide();
                await list();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }

    if ($('#dt-ent-cands').length) {
        let dtC;
        let cacheC = [];
        let elections = [];
        let currentElectionId = null;
        let potentialUsers = [];
        let allPotentialUsers = [];
        const $emptyState = $('#ent-cands-empty-state');
        const $readonlyState = $('#ent-cands-readonly-state');
        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const normalizeAudience = (v) => String(v || 'INTERNAL').toUpperCase();
        const normalizeUserType = (v) => String(v || 'INTERNAL').toUpperCase();
        const currentElection = () => elections.find(e => String(e.id) === String(currentElectionId)) || null;
        const canManageCurrentElection = () => !!(currentElection()?.can_manage_candidates);
        const currentAudience = () => normalizeAudience(currentElection()?.audience_mode || 'INTERNAL');
        const userTypeAllowedForAudience = (audienceMode, userType) => {
            const aud = normalizeAudience(audienceMode);
            const type = normalizeUserType(userType);
            if (aud === 'INTERNAL') return type === 'INTERNAL';
            if (aud === 'EXTERNAL') return type === 'EXTERNAL';
            return type === 'INTERNAL' || type === 'EXTERNAL';
        };

        const buildExternalUsername = (name) => {
            let base = String(name || '').toLowerCase();
            try {
                base = base.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            } catch (_) {
                // no-op
            }
            base = base.replace(/[^a-z0-9._-]+/g, '.').replace(/^\.+|\.+$/g, '');
            if (!base) base = 'external.candidate';
            const suffix = String(Date.now()).slice(-6);
            return `${base.slice(0, 40)}.${suffix}`.slice(0, 50);
        };
        const generateStrongPassword = () => {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
            let out = '';
            for (let i = 0; i < 14; i += 1) {
                out += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return out;
        };
        const updateElectionPreviewLink = () => {
            const id = parseInt($('#ent-cand-election').val(), 10);
            const hasElection = Number.isInteger(id) && id > 0;
            const href = hasElection ? `${basePath}/enterprise/preview.php?id=${encodeURIComponent(id)}` : '#';
            $('#ent-cand-preview-election')
                .attr('href', href)
                .toggleClass('disabled', !hasElection)
                .attr('aria-disabled', hasElection ? 'false' : 'true');
        };

        const resetCreateUserFields = () => {
            $('#ent-cand-new-username').val('');
            $('#ent-cand-new-email').val('');
            $('#ent-cand-new-password').val('');
            $('#ent-cand-create-user-wrap').addClass('d-none');
        };

        const setCandidatesUiState = (hasElection) => {
            const canManage = hasElection && canManageCurrentElection();
            $('#ent-btn-import-cands').prop('disabled', !canManage);
            $('#ent-btn-random-order').prop('disabled', !canManage);
            $('[data-bs-target="#modal-cand"]').prop('disabled', !canManage);
            $emptyState.toggleClass('d-none', hasElection);
            $readonlyState.toggleClass('d-none', !hasElection || canManage);
            updateElectionPreviewLink();
        };

        const applyAudienceUiRules = () => {
            const aud = currentAudience();
            const internalOnly = aud === 'INTERNAL';
            $('#ent-cand-create-user-toggle').prop('disabled', internalOnly);
            if (internalOnly) {
                $('#ent-cand-create-user-wrap').addClass('d-none');
            }
        };

        const renderRows = (rows) => {
            dtC?.destroy();
            $('#dt-ent-cands tbody').html(rows);
            dtC = $('#dt-ent-cands').DataTable({ order: [[0, 'asc']] });
        };

        const loadElections = async () => {
            elections = await fetchJson('ent-elections');
            if (!Array.isArray(elections) || elections.length === 0) {
                $('#ent-cand-election').html('<option value="">Aucune election disponible</option>');
                currentElectionId = null;
                setCandidatesUiState(false);
                renderRows('');
                return;
            }

            $('#ent-cand-election').html(elections.map(e => `<option value="${e.id}">${e.title} (${e.status})</option>`).join(''));
            const qs = new URLSearchParams(window.location.search);
            const wanted = parseInt(qs.get('election_id') || '', 10);
            currentElectionId = (wanted && elections.some(e => String(e.id) === String(wanted))) ? wanted : (elections[0]?.id || null);
            if (currentElectionId) {
                $('#ent-cand-election').val(String(currentElectionId));
                setCandidatesUiState(true);
                renderPotentialUsers();
                applyAudienceUiRules();
                updateElectionPreviewLink();
                return;
            }
            setCandidatesUiState(false);
            updateElectionPreviewLink();
        };

        const renderPotentialUsers = () => {
            const audience = currentAudience();
            potentialUsers = (allPotentialUsers || []).filter(u => userTypeAllowedForAudience(audience, u?.user_type || 'INTERNAL'));
            const options = (potentialUsers || []).map(u => {
                const label = u.label || u.full_name || u.username || `#${u.id}`;
                return `<option value="${u.id}">${esc(label)} (${esc(u.user_type || 'INTERNAL')})</option>`;
            }).join('');
            const emptyOption = '<option value="" disabled>Aucun utilisateur actif compatible</option>';
            $('#ent-cand-user').html('<option value="">Selection manuelle</option>' + (options || emptyOption));
        };

        const loadPotentialUsers = async () => {
            let users = [];
            try {
                users = await fetchJson('ent-users?scope=candidates');
            } catch (_) {
                users = [];
            }
            if (!Array.isArray(users) || users.length === 0) {
                const allUsers = await fetchJson('ent-users');
                users = (Array.isArray(allUsers) ? allUsers : []).filter(u => String(u?.status || '').toUpperCase() === 'ACTIVE');
            }
            allPotentialUsers = Array.isArray(users) ? users : [];
            renderPotentialUsers();
        };

        const list = async () => {
            currentElectionId = parseInt($('#ent-cand-election').val(), 10);
            renderPotentialUsers();
            applyAudienceUiRules();
            if (!Number.isInteger(currentElectionId) || currentElectionId <= 0) {
                cacheC = [];
                renderRows('');
                setCandidatesUiState(false);
                return;
            }
            cacheC = await fetchJson(`ent-candidates?election_id=${encodeURIComponent(currentElectionId)}`);
            const canManage = canManageCurrentElection();
            const rows = cacheC.map(c => `
                <tr>
                    <td>${c.display_order}</td>
                    <td>${c.full_name}${c.linked_username ? `<div class="small text-muted">@${c.linked_username}</div>` : ''}</td>
                    <td>${c.category || ''}</td>
                    <td>${c.is_validated ? 'Oui' : 'Non'}</td>
                    <td>${c.is_active ? 'Oui' : 'Non'}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary btn-ent-cand-edit" data-id="${c.id}" ${canManage ? '' : 'disabled'} title="${canManage ? 'Modifier' : 'Reserve au createur / SUPERADMIN'}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-ent-cand-del" data-id="${c.id}" ${canManage ? '' : 'disabled'} title="${canManage ? 'Supprimer' : 'Reserve au createur / SUPERADMIN'}"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join('');
            renderRows(rows);
            setCandidatesUiState(true);
            applyAudienceUiRules();
            updateElectionPreviewLink();
        };

        const open = (c) => {
            $('#ent-cand-id').val(c?.id || '');
            if (c?.user_id && $(`#ent-cand-user option[value="${c.user_id}"]`).length === 0) {
                const fallbackLabel = c?.linked_full_name || c?.linked_username || `Utilisateur #${c.user_id}`;
                $('#ent-cand-user').append(`<option value="${esc(c.user_id)}">${esc(fallbackLabel)} (hors audience)</option>`);
            }
            $('#ent-cand-user').val(c?.user_id || '');
            $('#ent-cand-name').val(c?.full_name || '');
            $('#ent-cand-category').val(c?.category || '');
            $('#ent-cand-order').val(c?.display_order ?? 0);
            $('#ent-cand-bio').val(c?.biography || '');
            $('#ent-cand-photo').val('');
            $('#ent-cand-validated').prop('checked', !!c?.is_validated);
            $('#ent-cand-active').prop('checked', !!c?.is_active);
            $('#modal-cand .modal-title').text(c ? `Modifier: ${c.full_name}` : 'Nouveau candidat');
        };

        $('#modal-cand').on('show.bs.modal', (ev) => {
            if (!canManageCurrentElection()) {
                toast('Seul le createur du scrutin ou un SUPERADMIN peut gerer les candidats.', 'error');
                ev.preventDefault();
                return;
            }
            if (!$('#ent-cand-id').val()) open(null);
        });
        $('#modal-cand').on('hidden.bs.modal', () => {
            $('#ent-cand-id').val('');
            $('#ent-cand-user').val('');
            $('#ent-cand-name').val('');
            $('#ent-cand-category').val('');
            $('#ent-cand-order').val('0');
            $('#ent-cand-bio').val('');
            $('#ent-cand-photo').val('');
            $('#ent-cand-validated').prop('checked', true);
            $('#ent-cand-active').prop('checked', true);
            resetCreateUserFields();
        });

        Promise.all([loadElections(), loadPotentialUsers()])
            .then(async () => {
                renderPotentialUsers();
                applyAudienceUiRules();
                await list();
            })
            .catch(e => toast(e.message, 'error'));

        $('#ent-cand-election').on('change', () => {
            list().catch(e => toast(e.message, 'error'));
        });
        $('#ent-cand-generate-password').on('click', () => {
            $('#ent-cand-new-password').val(generateStrongPassword());
        });
        $('#ent-cand-user').on('change', function () {
            const uid = parseInt($(this).val(), 10);
            if (!Number.isInteger(uid) || uid <= 0) return;
            const u = potentialUsers.find(x => String(x.id) === String(uid));
            if (!u) return;
            const label = (u.label || u.full_name || u.username || '').toString();
            if (label) $('#ent-cand-name').val(label);
            if (!$('#ent-cand-category').val().trim()) {
                $('#ent-cand-category').val((u.user_type || '').toString().toUpperCase());
            }
        });

        $('#ent-cand-create-user-toggle').on('click', () => {
            const hidden = $('#ent-cand-create-user-wrap').hasClass('d-none');
            $('#ent-cand-create-user-wrap').toggleClass('d-none', !hidden);
            if (hidden && !$('#ent-cand-new-username').val().trim()) {
                const fullName = $('#ent-cand-name').val().trim();
                if (fullName) $('#ent-cand-new-username').val(buildExternalUsername(fullName));
            }
        });

        $('#ent-cand-create-user-btn').on('click', async () => {
            if (!canManageCurrentElection()) {
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            if (currentAudience() === 'INTERNAL') {
                return toast('Scrutin INTERNAL: creation de candidat externe desactivee ici.', 'error');
            }
            const fullName = $('#ent-cand-name').val().trim();
            if (!fullName) return toast('Nom candidat requis avant creation utilisateur', 'error');

            let username = $('#ent-cand-new-username').val().trim();
            const email = $('#ent-cand-new-email').val().trim();
            let password = $('#ent-cand-new-password').val().trim();

            if (!username) username = buildExternalUsername(fullName);
            if (!password) password = generateStrongPassword();
            if (password.length < 8) return toast('Mot de passe externe: min 8 caracteres', 'error');

            try {
                const res = await fetchJson('ent-users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username,
                        email,
                        password,
                        full_name: fullName,
                        user_type: 'EXTERNAL',
                        status: 'ACTIVE',
                        roles: ['VOTER'],
                        groups: [],
                    }),
                });
                await loadPotentialUsers();
                const userId = Number(res.id || 0);
                if (userId > 0) $('#ent-cand-user').val(String(userId)).trigger('change');
                $('#ent-cand-category').val('EXTERNAL');
                toast(`Utilisateur externe cree (${username})`, 'success');
                try {
                    await navigator.clipboard.writeText(password);
                    toast('Mot de passe copie dans le presse-papiers', 'success');
                } catch (_) {
                    // ignore
                }
                window.prompt('Identifiants utilisateur externe (copie/colle):', `username=${username} / password=${password}`);
            } catch (err) {
                toast(err.message || 'Creation utilisateur impossible', 'error');
            }
        });

        $(document).on('click', '.btn-ent-cand-edit', function () {
            if (!canManageCurrentElection()) {
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            const id = $(this).data('id');
            const c = cacheC.find(x => String(x.id) === String(id));
            if (!c) return;
            open(c);
            const modalEl = document.getElementById('modal-cand');
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        });

        $(document).on('click', '.btn-ent-cand-del', async function () {
            if (!canManageCurrentElection()) {
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            const id = $(this).data('id');
            if (!confirm('Supprimer ?')) return;
            try {
                await fetchJson(`ent-candidates?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                toast('Supprime', 'success');
                await list();
            } catch (e) { toast(e.message, 'error'); }
        });

        $('#form-cand').on('submit', async (e) => {
            e.preventDefault();
            if (!canManageCurrentElection()) {
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            const id = $('#ent-cand-id').val();
            const file = $('#ent-cand-photo')[0].files[0];
            let photo = null;
            try {
                const electionId = parseInt($('#ent-cand-election').val(), 10);
                if (!Number.isInteger(electionId) || electionId <= 0) {
                    return toast('Aucune election selectionnee', 'error');
                }
                const linkedUserId = parseInt($('#ent-cand-user').val(), 10) || 0;
                if (linkedUserId > 0) {
                    const duplicate = cacheC.find(c => Number(c.user_id || 0) === linkedUserId && String(c.id) !== String(id || ''));
                    if (duplicate) {
                        return toast('Cet utilisateur est deja candidat pour ce scrutin', 'error');
                    }
                }
                if (file) {
                    const fd = new FormData();
                    fd.append('file', file);
                    const res = await fetchJson('ent-upload', { method: 'POST', body: fd });
                    photo = res.path;
                }
                const payload = {
                    election_id: electionId,
                    user_id: parseInt($('#ent-cand-user').val(), 10) || null,
                    full_name: $('#ent-cand-name').val().trim(),
                    category: $('#ent-cand-category').val().trim(),
                    biography: $('#ent-cand-bio').val(),
                    display_order: parseInt($('#ent-cand-order').val(), 10) || 0,
                    is_validated: $('#ent-cand-validated').is(':checked'),
                    is_active: $('#ent-cand-active').is(':checked'),
                };
                if (photo) payload.photo_path = photo;

                if (!id) {
                    await fetchJson('ent-candidates', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Candidat cree', 'success');
                } else {
                    await fetchJson(`ent-candidates?id=${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Mis a jour', 'success');
                }
                (bootstrap.Modal.getInstance(document.getElementById('modal-cand')) || new bootstrap.Modal(document.getElementById('modal-cand'))).hide();
                await list();
            } catch (err) { toast(err.message, 'error'); }
        });

        $('#ent-btn-import-cands').on('click', () => $('#ent-file-cands').click());
        $('#ent-file-cands').on('change', async function () {
            if (!canManageCurrentElection()) {
                this.value = '';
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            const f = this.files[0];
            if (!f) return;
            const fd = new FormData();
            fd.append('file', f);
            const eid = parseInt($('#ent-cand-election').val(), 10);
            if (!Number.isInteger(eid) || eid <= 0) {
                toast('Aucune election selectionnee', 'error');
                this.value = '';
                return;
            }
            try {
                const res = await fetchJson(`ent-candidates?election_id=${encodeURIComponent(eid)}`, { method: 'POST', body: fd });
                toast(`Import OK (imported=${res.imported} skipped=${res.skipped})`, 'success');
                await list();
            } catch (err) { toast(err.message, 'error'); }
            this.value = '';
        });

        $('#ent-btn-random-order').on('click', async () => {
            if (!canManageCurrentElection()) {
                return toast('Action reservee au createur du scrutin ou SUPERADMIN.', 'error');
            }
            const eid = parseInt($('#ent-cand-election').val(), 10);
            if (!Number.isInteger(eid) || eid <= 0) {
                return toast('Aucune election selectionnee', 'error');
            }
            try {
                await fetchJson(`ent-candidates?election_id=${encodeURIComponent(eid)}&action=randomize`, { method: 'POST' });
                toast('Ordre modifie', 'success');
                await list();
            } catch (err) { toast(err.message, 'error'); }
        });
    }

    if ($('#ent-results-election').length) {
        let resultsChart = null;
        const loadElections = async () => {
            const elections = await fetchJson('ent-elections');
            $('#ent-results-election').html(elections.map(e => `<option value="${e.id}">${e.title} (${e.status})</option>`).join(''));
        };

        const render = async () => {
            const id = $('#ent-results-election').val();
            if (!id) return;
            const d = await fetchJson(`ent-results?election_id=${encodeURIComponent(id)}`);
            $('#ent-stat-eligible').text(d.eligible);
            $('#ent-stat-voted').text(d.voted);
            $('#ent-stat-rate').text(`${d.rate}%`);

            const rows = (d.results || []).map(r => `<tr><td>${r.label}</td><td class="text-end">${r.count}</td></tr>`).join('');
            $('#ent-results-table').html(`
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Option</th><th class="text-end">Votes</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);

            const canvas = document.getElementById('ent-results-chart');
            const ctx = canvas?.getContext?.('2d');
            if (ctx && window.Chart) {
                const labels = (d.results || []).map(r => r.label);
                const values = (d.results || []).map(r => Number(r.count || 0));
                const type = (d.election?.type || '').toUpperCase() === 'YESNO' ? 'doughnut' : 'bar';

                if (resultsChart) {
                    resultsChart.destroy();
                    resultsChart = null;
                }

                resultsChart = new Chart(ctx, {
                    type,
                    data: {
                        labels,
                        datasets: [{
                            label: 'Votes',
                            data: values,
                            backgroundColor: type === 'bar'
                                ? 'rgba(59, 125, 221, 0.35)'
                                : ['rgba(28,187,140,0.6)', 'rgba(220,53,69,0.6)'],
                            borderColor: type === 'bar' ? (window.theme?.primary || '#3b7ddd') : '#ffffff',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: type !== 'bar' } },
                        scales: type === 'bar'
                            ? { y: { beginAtZero: true, ticks: { precision: 0 } } }
                            : {},
                    },
                });
            }
        };

        loadElections().then(render).catch(e => toast(e.message, 'error'));
        $('#ent-results-election').on('change', () => render().catch(e => toast(e.message, 'error')));
        $('#ent-btn-export-results').on('click', () => {
            const id = $('#ent-results-election').val();
            if (!id) return;
            window.location.href = apiUrl(`ent-results?election_id=${encodeURIComponent(id)}&format=csv`);
        });
        $('#ent-btn-print-report').on('click', () => window.print());
    }

    if ($('#dt-ent-audit').length) {
        fetchJson('ent-audit').then(list => {
            const rows = list.map(a => `
                <tr>
                    <td>${new Date(a.created_at).toLocaleString()}</td>
                    <td>${a.actor || ''}</td>
                    <td>${a.action}</td>
                    <td>${a.entity_type || ''} ${a.entity_id || ''}</td>
                    <td><code class="small">${(a.metadata_json || '').toString().slice(0, 180)}</code></td>
                </tr>`).join('');
            $('#dt-ent-audit tbody').html(rows);
            $('#dt-ent-audit').DataTable({ order: [[0, 'desc']] });
        }).catch(e => toast(e.message, 'error'));
    }

    if ($('#dt-ent-notifs').length) {
        let dtN;
        let cacheN = [];
        const list = async () => {
            cacheN = await fetchJson('ent-notifications');
            const rows = cacheN.map(n => `
                <tr>
                    <td>${n.title}</td>
                    <td>${n.level}</td>
                    <td>${n.audience_scope || 'ALL'}</td>
                    <td>${n.is_push ? 'Oui' : 'Non'}</td>
                    <td>${n.is_active ? 'Oui' : 'Non'}</td>
                    <td>${n.target_url ? `<a href="${n.target_url}" target="_blank" rel="noopener">Ouvrir</a>` : ''}</td>
                    <td>${n.sent_count || 0}${n.sent_at ? `<div class="small text-muted">${new Date(n.sent_at).toLocaleString()}</div>` : ''}</td>
                    <td>${n.starts_at ? new Date(n.starts_at).toLocaleString() : ''}</td>
                    <td>${n.ends_at ? new Date(n.ends_at).toLocaleString() : ''}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary btn-ent-notif-edit" data-id="${n.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-ent-notif-del" data-id="${n.id}"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join('');
            dtN?.destroy();
            $('#dt-ent-notifs tbody').html(rows);
            dtN = $('#dt-ent-notifs').DataTable({ order: [[0, 'desc']] });
        };
        list().catch(e => toast(e.message, 'error'));

        const open = (n) => {
            $('#ent-notif-id').val(n?.id || '');
            $('#ent-notif-title').val(n?.title || '');
            $('#ent-notif-body').val(n?.body || '');
            $('#ent-notif-level').val(n?.level || 'INFO');
            $('#ent-notif-audience').val(n?.audience_scope || 'ALL');
            $('#ent-notif-target').val(n?.target_url || '');
            $('#ent-notif-is-push').prop('checked', !!n?.is_push);
            $('#ent-notif-send-now').prop('checked', false);
            $('#ent-notif-active').prop('checked', n ? !!n.is_active : true);
            $('#ent-notif-starts').val((n?.starts_at || '').replace(' ', 'T'));
            $('#ent-notif-ends').val((n?.ends_at || '').replace(' ', 'T'));
            $('#modal-notif .modal-title').text(n ? `Modifier: ${n.title}` : 'Nouvelle notification');
        };

        $('#modal-notif').on('hidden.bs.modal', () => open(null));

        $(document).on('click', '.btn-ent-notif-edit', function () {
            const id = $(this).data('id');
            const n = cacheN.find(x => String(x.id) === String(id));
            if (!n) return;
            open(n);
            const modalEl = document.getElementById('modal-notif');
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        });

        $(document).on('click', '.btn-ent-notif-del', async function () {
            const id = $(this).data('id');
            if (!confirm('Supprimer ?')) return;
            try {
                await fetchJson(`ent-notifications?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
                toast('Supprimée', 'success');
                await list();
            } catch (e) { toast(e.message, 'error'); }
        });

        $('#form-notif').on('submit', async (e) => {
            e.preventDefault();
            const id = $('#ent-notif-id').val();
            const payload = {
                title: $('#ent-notif-title').val().trim(),
                body: $('#ent-notif-body').val(),
                level: $('#ent-notif-level').val(),
                audience_scope: $('#ent-notif-audience').val() || 'ALL',
                target_url: $('#ent-notif-target').val().trim(),
                is_push: $('#ent-notif-is-push').is(':checked'),
                send_push_now: $('#ent-notif-send-now').is(':checked'),
                is_active: $('#ent-notif-active').is(':checked'),
                starts_at: $('#ent-notif-starts').val(),
                ends_at: $('#ent-notif-ends').val(),
            };
            try {
                if (!id) {
                    await fetchJson('ent-notifications', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Notification creee', 'success');
                } else {
                    await fetchJson(`ent-notifications?id=${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    toast('Mise a jour', 'success');
                }
                (bootstrap.Modal.getInstance(document.getElementById('modal-notif')) || new bootstrap.Modal(document.getElementById('modal-notif'))).hide();
                await list();
            } catch (err) { toast(err.message, 'error'); }
        });
    }
})();







