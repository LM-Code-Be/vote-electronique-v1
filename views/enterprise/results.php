<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Résultats';
/** @var string|null $active */
$active = isset($active) ? (string)$active : 'results';
/** @var array $cards */
$cards = isset($cards) && is_array($cards) ? $cards : [];
/** @var int $selectedId */
$selectedId = isset($selectedId) ? (int)$selectedId : 0;

$items = [];
foreach ($cards as $card) {
    $e = (array)($card['election'] ?? []);
    $items[] = [
        'id' => (int)($e['id'] ?? 0),
        'title' => (string)($e['title'] ?? ''),
        'status' => (string)($e['status'] ?? 'DRAFT'),
        'type' => (string)($e['type'] ?? 'SINGLE'),
        'end_at' => (string)($e['end_at'] ?? ''),
        'eligible' => !empty($card['eligible']),
        'voted' => !empty($card['voted']),
        'results_available' => !empty($card['results_available']),
    ];
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/partial/head.php'; ?>
</head>
<body class="layout-top-nav">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <?php include __DIR__ . '/partial/nav.php'; ?>

    <main class="content" id="main" tabindex="-1">
        <div class="container mt-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h3 mb-0">Résultats des campagnes</h1>
                <span class="text-muted small"><?=count($items)?> campagne(s)</span>
            </div>

            <?php include __DIR__ . '/partial/notifs.php'; ?>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="h6 mb-0">Campagnes</h2>
                        </div>
                        <div class="card-body">
                            <label class="form-label small" for="results-election">Sélectionner une campagne</label>
                            <select id="results-election" class="form-select mb-3">
                                <?php foreach ($items as $it): ?>
                                    <option value="<?=$it['id']?>" <?=$selectedId === (int)$it['id'] ? 'selected' : ''?>>
                                        <?=htmlspecialchars($it['title'])?> (<?=htmlspecialchars($it['status'])?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div id="results-election-meta" class="small text-muted"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h2 class="h6 mb-0" id="results-title">Résultats</h2>
                            <a class="btn btn-sm btn-outline-secondary" id="results-vote-link" href="#"><i class="bi bi-box-arrow-up-right me-1"></i>Ouvrir la campagne</a>
                        </div>
                        <div class="card-body">
                            <div id="results-alert"></div>
                            <div id="results-stats" class="row g-2 mb-3 d-none">
                                <div class="col-md-4">
                                    <div class="p-2 bg-light rounded text-center">
                                        <div class="text-muted small">Éligibles</div>
                                        <div class="fw-bold" id="results-kpi-eligible">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 bg-light rounded text-center">
                                        <div class="text-muted small">Votes</div>
                                        <div class="fw-bold" id="results-kpi-voted">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 bg-light rounded text-center">
                                        <div class="text-muted small">Participation</div>
                                        <div class="fw-bold" id="results-kpi-rate">0%</div>
                                    </div>
                                </div>
                            </div>

                            <div id="results-body" class="d-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>

    <script>
    (() => {
        const basePath = (<?=json_encode(APP_BASE_PATH, JSON_UNESCAPED_SLASHES)?> || '').replace(/\/$/, '');
        const apiUrl = (path) => `${basePath}/api/${String(path).replace(/^\/+/, '')}`;
        const cards = <?=json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;

        const selectEl = document.getElementById('results-election');
        const metaEl = document.getElementById('results-election-meta');
        const titleEl = document.getElementById('results-title');
        const voteLinkEl = document.getElementById('results-vote-link');
        const alertEl = document.getElementById('results-alert');
        const statsWrap = document.getElementById('results-stats');
        const bodyEl = document.getElementById('results-body');

        const setAlert = (type, text) => {
            alertEl.innerHTML = text ? `<div class="alert alert-${type} mb-3">${text}</div>` : '';
        };

        const selectedCard = () => {
            const id = parseInt(selectEl?.value || '0', 10);
            return cards.find((c) => Number(c.id) === id) || null;
        };

        const renderMeta = (card) => {
            if (!card) {
                metaEl.textContent = 'Aucune campagne disponible.';
                return;
            }
            const eligibility = card.eligible ? 'Éligible' : 'Non éligible';
            const voteState = card.voted ? 'Vote enregistré' : 'Pas encore voté';
            const results = card.results_available ? 'Résultats disponibles' : 'Résultats après clôture';
            metaEl.innerHTML = `
                <div><i class="bi bi-person-check me-1"></i>${eligibility}</div>
                <div><i class="bi bi-check2-circle me-1"></i>${voteState}</div>
                <div><i class="bi bi-bar-chart-line me-1"></i>${results}</div>
            `;
        };

        const renderResultsTable = (rows) => {
            if (!Array.isArray(rows) || rows.length === 0) {
                bodyEl.innerHTML = '<div class="text-muted">Aucun vote comptabilisé pour l’instant.</div>';
                return;
            }
            const max = Math.max(...rows.map((r) => Number(r.count || 0)), 1);
            const tr = rows.map((r) => {
                const count = Number(r.count || 0);
                const pct = Math.round((count / max) * 100);
                return `
                    <tr>
                        <td>${String(r.label || '')}</td>
                        <td class="text-end">${count}</td>
                        <td style="width:45%">
                            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="${max}" aria-valuenow="${count}">
                                <div class="progress-bar" style="width:${pct}%"></div>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            bodyEl.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Option</th><th class="text-end">Votes</th><th>Répartition</th></tr></thead>
                        <tbody>${tr}</tbody>
                    </table>
                </div>
            `;
        };

        const load = async () => {
            const card = selectedCard();
            renderMeta(card);
            if (!card) {
                titleEl.textContent = 'Résultats';
                voteLinkEl.href = `${basePath}/enterprise/elections.php`;
                setAlert('secondary', 'Aucune campagne à afficher.');
                statsWrap.classList.add('d-none');
                bodyEl.classList.add('d-none');
                bodyEl.innerHTML = '';
                return;
            }

            titleEl.textContent = `Résultats • ${card.title}`;
            voteLinkEl.href = `${basePath}/enterprise/vote.php?id=${encodeURIComponent(card.id)}`;

            try {
                const res = await fetch(apiUrl(`ent-results?election_id=${encodeURIComponent(card.id)}`), { credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.error) {
                    throw new Error(data.error || `HTTP ${res.status}`);
                }

                if (!data.results_available) {
                    const msg = data.results_message || 'Les résultats ne sont pas encore visibles pour ce scrutin.';
                    setAlert('info', `<i class="bi bi-info-circle me-1"></i>${msg}`);
                    statsWrap.classList.add('d-none');
                    bodyEl.classList.add('d-none');
                    bodyEl.innerHTML = '';
                    return;
                }

                setAlert('', '');
                document.getElementById('results-kpi-eligible').textContent = String(data.eligible ?? 0);
                document.getElementById('results-kpi-voted').textContent = String(data.voted ?? 0);
                document.getElementById('results-kpi-rate').textContent = `${data.rate ?? 0}%`;
                statsWrap.classList.remove('d-none');
                bodyEl.classList.remove('d-none');
                renderResultsTable(data.results || []);
            } catch (err) {
                setAlert('danger', `<i class="bi bi-exclamation-triangle me-1"></i>${err.message || 'Erreur de chargement des résultats.'}`);
                statsWrap.classList.add('d-none');
                bodyEl.classList.add('d-none');
                bodyEl.innerHTML = '';
            }
        };

        selectEl?.addEventListener('change', () => {
            const card = selectedCard();
            const nextId = card ? String(card.id) : '';
            const nextUrl = `${basePath}/enterprise/results.php${nextId ? `?id=${encodeURIComponent(nextId)}` : ''}`;
            window.history.replaceState({}, '', nextUrl);
            load().catch(() => {});
        });

        load().catch(() => {});
    })();
    </script>
</body>
</html>

