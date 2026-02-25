<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Aperçu';
/** @var string|null $active */
$active = isset($active) ? (string)$active : 'elections';
/** @var array $election */
$election = isset($election) && is_array($election) ? $election : [];
/** @var bool $open */
$open = !empty($open);
/** @var string $type */
$type = isset($type) ? (string)$type : (string)($election['type'] ?? 'SINGLE');
/** @var array $candidates */
$candidates = isset($candidates) && is_array($candidates) ? $candidates : [];
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
                        Aperçu votant • <?=htmlspecialchars($type)?> • <?=!empty($election['is_anonymous']) ? 'Anonyme' : 'Nominatif'?>
                        <?=!empty($election['allow_vote_change']) ? ' • Modifiable' : ''?>
                    </div>
                </div>
                <span class="badge text-bg-<?= $open ? 'success' : 'secondary' ?>"><?= $open ? 'Ouvert' : 'Fermé' ?></span>
            </div>

            <div class="alert alert-info">
                Ceci est un <strong>aperçu</strong> (aucun vote ne sera enregistré).
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if ($type === 'YESNO'): ?>
                        <div class="d-flex gap-3">
                            <button class="btn btn-outline-success" disabled>Oui</button>
                            <button class="btn btn-outline-danger" disabled>Non</button>
                        </div>
                    <?php elseif ($type === 'MULTI'): ?>
                        <div class="mb-2 text-muted small">Sélection multiple<?=!empty($election['max_choices']) ? ' (max ' . (int)$election['max_choices'] . ')' : ''?>.</div>
                        <div class="row g-3">
                            <?php foreach ($candidates as $c): ?>
                                <div class="col-md-6">
                                    <div class="card h-100 p-3">
                                        <div class="d-flex align-items-start">
                                            <input class="form-check-input me-2 mt-1" type="checkbox" disabled>
                                            <div>
                                                <div class="fw-semibold"><?=htmlspecialchars((string)$c['full_name'])?></div>
                                                <div class="text-muted small"><?=htmlspecialchars((string)($c['biography'] ?? ''))?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($type === 'RANKED'): ?>
                        <div class="alert alert-secondary">Classement (aperçu).</div>
                        <?php $i=1; foreach ($candidates as $c): ?>
                            <div class="mb-2">
                                <label class="form-label">Rang <?=$i?></label>
                                <select class="form-select" disabled>
                                    <option>(aperçu)</option>
                                </select>
                            </div>
                        <?php $i++; endforeach; ?>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($candidates as $c): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 p-3">
                                        <div class="d-flex align-items-start">
                                            <input class="form-check-input me-2 mt-1" type="radio" disabled>
                                            <div>
                                                <div class="fw-semibold"><?=htmlspecialchars((string)$c['full_name'])?></div>
                                                <div class="text-muted small"><?=htmlspecialchars((string)($c['biography'] ?? ''))?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>


