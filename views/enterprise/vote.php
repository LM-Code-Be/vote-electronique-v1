<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Voter';
/** @var string|null $active */
$active = isset($active) ? (string)$active : 'elections';
/** @var array $election */
$election = isset($election) && is_array($election) ? $election : [];
/** @var bool $open */
$open = !empty($open);
/** @var bool $eligible */
$eligible = !empty($eligible);
/** @var bool $voted */
$voted = !empty($voted);
/** @var string|null $error */
$error = isset($error) && is_string($error) ? $error : null;
/** @var string|null $success */
$success = isset($success) && is_string($success) ? $success : null;
/** @var string|null $receipt */
$receipt = isset($receipt) && is_string($receipt) ? $receipt : null;
/** @var string $type */
$type = isset($type) ? (string)$type : (string)($election['type'] ?? 'SINGLE');
/** @var array $candidates */
$candidates = isset($candidates) && is_array($candidates) ? $candidates : [];
$status = (string)($election['status'] ?? 'DRAFT');
$allowChange = !empty($election['allow_vote_change']);
$endAt = (string)($election['end_at'] ?? '');
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
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <h1 class="h3 mb-1"><?=htmlspecialchars((string)($election['title'] ?? ''))?></h1>
                    <div class="text-muted small">
                        <?=htmlspecialchars($type)?> • <?=!empty($election['is_anonymous']) ? 'Anonyme' : 'Nominatif'?>
                        <?=$allowChange ? ' • Modifiable' : ''?>
                    </div>
                </div>
                <span class="badge text-bg-<?= $open ? 'success' : 'secondary' ?>"><?= $open ? 'Ouvert' : 'Fermé' ?></span>
            </div>

            <?php include __DIR__ . '/partial/notifs.php'; ?>

            <?php if (!$eligible): ?>
                <div class="alert alert-warning">Tu n’es pas éligible à ce scrutin.</div>
            <?php endif; ?>
            <?php if ($voted && !$allowChange): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Tu as déjà voté. Ce vote n’est pas modifiable.</div>
            <?php elseif ($voted && $allowChange && $open): ?>
                <div class="alert alert-info"><i class="bi bi-arrow-repeat me-1"></i>Tu peux modifier ton vote jusqu’au <strong><?=htmlspecialchars($endAt)?></strong>.</div>
            <?php elseif ($voted && $allowChange && !$open): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Ton vote est enregistré. La campagne est clôturée: modification impossible.</div>
            <?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>

            <?php if ($receipt): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h5 mb-2">Accusé de réception</h2>
                        <p class="text-muted mb-2">Conserve ce code (utile pour vérifier ton dépôt en mode anonyme).</p>
                        <div class="p-3 bg-light rounded font-monospace" style="word-break:break-all;"><?=$receipt?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($open && $eligible && (!$voted || $allowChange)): ?>
                <div class="card">
                    <div class="card-body">
                        <form method="post" id="voteForm">
                            <?=csrf_field()?>
                            <?php if ($type === 'YESNO'): ?>
                                <div class="mb-3">
                                    <label class="form-label">Votre choix</label>
                                    <div class="d-flex gap-2 flex-wrap page-actions">
                                        <label class="btn btn-outline-success w-50">
                                            <input class="d-none" type="radio" name="yesno" value="YES" required> Oui
                                        </label>
                                        <label class="btn btn-outline-danger w-50">
                                            <input class="d-none" type="radio" name="yesno" value="NO" required> Non
                                        </label>
                                    </div>
                                </div>
                            <?php elseif ($type === 'MULTI'): ?>
                                <div class="mb-2 text-muted small">
                                    Sélection multiple<?=!empty($election['max_choices']) ? ' (max ' . (int)$election['max_choices'] . ')' : ''?>.
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($candidates as $c): ?>
                                        <div class="col-md-6">
                                            <label class="card h-100 p-3">
                                                <div class="d-flex align-items-start">
                                                    <input class="form-check-input me-2 mt-1" type="checkbox" name="choices[]" value="<?= (int)$c['id'] ?>">
                                                    <div>
                                                        <div class="fw-semibold"><?=htmlspecialchars((string)$c['full_name'])?></div>
                                                        <div class="text-muted small"><?=htmlspecialchars((string)($c['biography'] ?? ''))?></div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($type === 'RANKED'): ?>
                                <div class="alert alert-secondary">Classement : choisis un candidat par rang.</div>
                                <?php $i = 1; foreach ($candidates as $c): ?>
                                    <div class="mb-2">
                                        <label class="form-label">Rang <?=$i?></label>
                                        <select class="form-select" name="ranking[]" required>
                                            <option value="">—</option>
                                            <?php foreach ($candidates as $opt): ?>
                                                <option value="<?= (int)$opt['id'] ?>"><?=htmlspecialchars((string)$opt['full_name'])?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php $i++; endforeach; ?>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($candidates as $c): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <label class="card h-100 p-3">
                                                <div class="d-flex align-items-start">
                                                    <input class="form-check-input me-2 mt-1" type="radio" name="choice" value="<?= (int)$c['id'] ?>" required>
                                                    <div>
                                                        <div class="fw-semibold"><?=htmlspecialchars((string)$c['full_name'])?></div>
                                                        <div class="text-muted small"><?=htmlspecialchars((string)($c['biography'] ?? ''))?></div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-check mt-3">
                                <input class="form-check-input" id="confirmVote" type="checkbox" required>
                                <label class="form-check-label" for="confirmVote">Je confirme mon vote.</label>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-success" id="submitBtn"><i class="bi bi-check-circle me-1"></i>Soumettre</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($eligible && in_array($status, ['CLOSED', 'ARCHIVED'], true)): ?>
                <div class="card">
                    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <div class="text-muted">
                            Le scrutin est clôturé. Les résultats sont disponibles dans l’onglet dédié.
                        </div>
                        <a class="btn btn-outline-info" href="<?=htmlspecialchars(app_url('/enterprise/results.php?id=' . (int)($election['id'] ?? 0)))?>">
                            <i class="bi bi-bar-chart-line me-1"></i>Voir les résultats
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>
    <script>
    document.getElementById('voteForm')?.addEventListener('submit', (e) => {
        if (!confirm('Confirmer l’envoi du vote ?')) e.preventDefault();
    });
    </script>
</body>
</html>



