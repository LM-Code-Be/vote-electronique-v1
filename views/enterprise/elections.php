<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Scrutins';
/** @var string|null $active */
$active = isset($active) ? (string)$active : 'elections';
/** @var array $cards */
$cards = isset($cards) && is_array($cards) ? $cards : [];

$ongoing = isset($cards['ongoing']) && is_array($cards['ongoing']) ? $cards['ongoing'] : [];
$upcoming = isset($cards['upcoming']) && is_array($cards['upcoming']) ? $cards['upcoming'] : [];
$past = isset($cards['past']) && is_array($cards['past']) ? $cards['past'] : [];
$total = count($ongoing) + count($upcoming) + count($past);
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
        <div class="container">
            <div class="d-flex align-items-center justify-content-between mb-3 mt-3">
                <h1 class="h3 mb-0">Mes campagnes</h1>
                <span class="text-muted small"><?=$total?> campagne(s)</span>
            </div>

            <?php include __DIR__ . '/partial/notifs.php'; ?>

            <?php
            $sections = [
                'ongoing' => ['title' => 'En cours', 'icon' => 'bi-broadcast', 'items' => $ongoing],
                'upcoming' => ['title' => 'À venir', 'icon' => 'bi-calendar-event', 'items' => $upcoming],
                'past' => ['title' => 'Passées', 'icon' => 'bi-archive', 'items' => $past],
            ];
            ?>

            <?php foreach ($sections as $section): ?>
                <section class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="h5 mb-0"><i class="bi <?=$section['icon']?> me-1"></i><?=$section['title']?></h2>
                        <span class="text-muted small"><?=count($section['items'])?></span>
                    </div>

                    <?php if (!$section['items']): ?>
                        <div class="card">
                            <div class="card-body text-muted small">Aucune campagne dans cette section.</div>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($section['items'] as $card):
                                $el = (array)($card['election'] ?? []);
                                $open = !empty($card['open']);
                                $eligible = !empty($card['eligible']);
                                $voted = !empty($card['voted']);
                                $canResults = !empty($card['can_results']);
                                $phase = (string)($card['phase'] ?? 'upcoming');
                                $id = (int)($el['id'] ?? 0);
                                $status = (string)($el['status'] ?? 'DRAFT');
                                $allowChange = !empty($el['allow_vote_change']);
                                ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h3 class="h5 mb-1"><?=htmlspecialchars((string)($el['title'] ?? ''))?></h3>
                                                    <div class="text-muted small">
                                                        <?=htmlspecialchars((string)($el['type'] ?? 'SINGLE'))?>
                                                        • <?=!empty($el['is_anonymous']) ? 'Anonyme' : 'Nominatif'?>
                                                    </div>
                                                </div>
                                                <?php if ($status === 'PUBLISHED' && $open): ?>
                                                    <span class="badge text-bg-success">Ouvert</span>
                                                <?php elseif ($phase === 'upcoming'): ?>
                                                    <span class="badge text-bg-warning">Programmé</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary">Clôturé</span>
                                                <?php endif; ?>
                                            </div>

                                            <p class="mt-2 mb-3 text-muted small"><?=nl2br(htmlspecialchars((string)($el['description'] ?? '')))?></p>

                                            <div class="small text-muted">
                                                <div><i class="bi bi-clock me-1"></i><?=htmlspecialchars((string)($el['start_at'] ?? ''))?> → <?=htmlspecialchars((string)($el['end_at'] ?? ''))?></div>
                                                <?php if ($allowChange): ?>
                                                    <div><i class="bi bi-arrow-repeat me-1"></i>Modification autorisée jusqu’à la date de fin</div>
                                                <?php else: ?>
                                                    <div><i class="bi bi-lock me-1"></i>Vote non modifiable après envoi</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent border-0 pt-0">
                                            <div class="d-grid gap-2">
                                                <?php if (!$eligible): ?>
                                                    <button class="btn btn-outline-secondary w-100" disabled>Non éligible</button>
                                                <?php elseif (!$open): ?>
                                                    <button class="btn btn-outline-secondary w-100" disabled><?= $phase === 'upcoming' ? 'Pas encore ouvert' : 'Clôturé' ?></button>
                                                <?php elseif ($voted && !$allowChange): ?>
                                                    <button class="btn btn-outline-success w-100" disabled><i class="bi bi-check-circle me-1"></i>Déjà voté</button>
                                                <?php else: ?>
                                                    <a class="btn btn-primary w-100" href="<?=htmlspecialchars(app_url('/enterprise/vote.php?id=' . $id))?>">
                                                        <?= $voted ? 'Modifier mon vote' : 'Voter maintenant' ?>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($canResults): ?>
                                                    <a class="btn btn-outline-info w-100" href="<?=htmlspecialchars(app_url('/enterprise/results.php?id=' . $id))?>">
                                                        <i class="bi bi-bar-chart-line me-1"></i>Voir résultats
                                                    </a>
                                                <?php elseif ($eligible): ?>
                                                    <button class="btn btn-outline-secondary w-100" disabled>Résultats disponibles après clôture</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>
