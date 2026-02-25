<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Candidats';
/** @var bool $includeDataTables */
$includeDataTables = !empty($includeDataTables);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/partial/head.php'; ?>
</head>
<body class="layout-fixed">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <div class="wrapper">
        <?php include __DIR__ . '/partial/sidebar.php'; ?>
        <div class="main">
            <?php include __DIR__ . '/partial/topbar.php'; ?>

            <main class="content" id="main" tabindex="-1">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3 page-header">
                        <h1 class="h3 mb-0">Candidats</h1>
                        <div class="d-flex gap-2 flex-wrap page-actions">
                            <select class="form-select" id="ent-cand-election" style="min-width:320px"></select>
                            <a class="btn btn-outline-secondary disabled" id="ent-cand-preview-election" href="#" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>Apercu scrutin</a>
                            <input type="file" id="ent-file-cands" accept=".csv" hidden>
                            <button class="btn btn-outline-primary" id="ent-btn-import-cands"><i class="bi bi-upload me-1"></i>Importer CSV</button>
                            <button class="btn btn-outline-secondary" id="ent-btn-random-order"><i class="bi bi-shuffle me-1"></i>Aléatoire</button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-cand"><i class="bi bi-plus-circle me-1"></i>Nouveau</button>
                        </div>
                    </div>

                    <div id="ent-cands-empty-state" class="alert alert-warning d-none">
                        Aucune campagne disponible. Crée d’abord une élection dans l’onglet <strong>Élections</strong>.
                    </div>
                    <div id="ent-cands-readonly-state" class="alert alert-info d-none">
                        Lecture seule: seuls le createur du scrutin et le SUPERADMIN peuvent gerer les candidats.
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-cands" class="table table-bordered w-100">
                                <thead><tr><th>Ordre</th><th>Nom</th><th>Catégorie</th><th>Validé</th><th>Actif</th><th style="width:160px">Actions</th></tr></thead>
                                <tbody></tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <?php include __DIR__ . '/partial/footer.php'; ?>
        </div>
    </div>

    <div class="modal fade" id="modal-cand" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form class="modal-content" id="form-cand">
                <div class="modal-header">
                    <h5 class="modal-title">Candidat</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" id="ent-cand-id">
                    <div class="col-md-6"><label class="form-label">Utilisateur (potentiel candidat)</label><select class="form-select" id="ent-cand-user"><option value="">Selection manuelle</option></select></div>
                    <div class="col-md-6"><label class="form-label">Nom</label><input class="form-control" id="ent-cand-name" required></div>
                    <div class="col-12 d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="ent-cand-create-user-toggle"><i class="bi bi-person-plus me-1"></i>Creer un utilisateur externe</button>
                        <span class="text-muted small">A utiliser si le candidat n'existe pas encore dans la base.</span>
                    </div>
                    <div class="col-12 d-none" id="ent-cand-create-user-wrap">
                        <div class="border rounded p-3 bg-light">
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label mb-1">Username</label><input class="form-control" id="ent-cand-new-username" placeholder="auto si vide"></div>
                                <div class="col-md-4"><label class="form-label mb-1">Email (optionnel)</label><input type="email" class="form-control" id="ent-cand-new-email"></div>
                                <div class="col-md-4"><label class="form-label mb-1">Mot de passe</label><input class="form-control" id="ent-cand-new-password" placeholder="auto si vide"></div>
                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="ent-cand-generate-password">Generer mot de passe</button>
                                    <button class="btn btn-outline-primary btn-sm" type="button" id="ent-cand-create-user-btn">Creer puis lier ce candidat</button>
                                </div>
                                <div class="col-12"><div class="form-text">Si le mot de passe est vide, il est genere automatiquement.</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3"><label class="form-label">Catégorie</label><input class="form-control" id="ent-cand-category" placeholder="ex: Liste A"></div>
                    <div class="col-md-3"><label class="form-label">Ordre</label><input type="number" class="form-control" id="ent-cand-order" value="0"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" id="ent-cand-bio" rows="3"></textarea></div>
                    <div class="col-md-6"><label class="form-label">Photo</label><input type="file" class="form-control" id="ent-cand-photo" accept="image/*"></div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ent-cand-validated" checked>
                            <label class="form-check-label" for="ent-cand-validated">Validé</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ent-cand-active" checked>
                            <label class="form-check-label" for="ent-cand-active">Actif</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>





