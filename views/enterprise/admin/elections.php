<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Élections';
/** @var bool $includeDataTables */
$includeDataTables = !empty($includeDataTables);
/** @var bool $canManageElections */
$canManageElections = !empty($canManageElections);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/partial/head.php'; ?>
</head>
<body class="layout-fixed" data-can-manage-elections="<?=$canManageElections ? '1' : '0'?>">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <div class="wrapper">
        <?php include __DIR__ . '/partial/sidebar.php'; ?>
        <div class="main">
            <?php include __DIR__ . '/partial/topbar.php'; ?>

            <main class="content" id="main" tabindex="-1">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3 page-header">
                        <div>
                            <h1 class="h3 mb-0">Élections</h1>
                            <div class="text-muted small">Table filtrable + assistant de création (wizard).</div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap page-actions">
                            <select class="form-select form-select-sm" id="ent-filter-type" aria-label="Filtrer par type">
                                <option value="">Tous types</option>
                                <option value="SINGLE">SINGLE</option>
                                <option value="MULTI">MULTI</option>
                                <option value="YESNO">YESNO</option>
                                <option value="RANKED">RANKED</option>
                            </select>
                            <select class="form-select form-select-sm" id="ent-filter-audience" aria-label="Filtrer par audience">
                                <option value="">Toutes audiences</option>
                                <option value="INTERNAL">INTERNAL</option>
                                <option value="HYBRID">HYBRID</option>
                                <option value="EXTERNAL">EXTERNAL</option>
                            </select>
                            <select class="form-select form-select-sm" id="ent-filter-status" aria-label="Filtrer par statut">
                                <option value="">Tous</option>
                                <option value="DRAFT">DRAFT</option>
                                <option value="PUBLISHED">PUBLISHED</option>
                                <option value="CLOSED">CLOSED</option>
                                <option value="ARCHIVED">ARCHIVED</option>
                            </select>
                            <?php if ($canManageElections): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-election">
                                    <i class="bi bi-plus-circle me-1"></i>Nouvelle élection
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$canManageElections): ?>
                        <div class="alert alert-info">Mode lecture seule: seuls le createur du scrutin et le SUPERADMIN peuvent le modifier.</div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-elections" class="table table-bordered w-100">
                                <thead>
                                    <tr>
                                        <th>Titre</th><th>Type</th><th>Audience</th><th>Anonyme</th><th>Statut</th><th>Début</th><th>Fin</th><th style="width:200px">Actions</th>
                                    </tr>
                                </thead>
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

    <div class="modal fade" id="modal-election" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form class="modal-content" id="form-election">
                <div class="modal-header">
                    <h5 class="modal-title">Créer / modifier une élection</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ent-election-id">

                    <ul class="nav nav-pills wizard-steps mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" type="button" data-step="1"><span class="step-badge">1</span>Infos</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" type="button" data-step="2"><span class="step-badge">2</span>Règles</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" type="button" data-step="3"><span class="step-badge">3</span>Éligibilité</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" type="button" data-step="4"><span class="step-badge">4</span>Résumé</button>
                        </li>
                    </ul>

                    <div class="ent-wizard-step" data-step="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Titre</label>
                                <input class="form-control" id="ent-election-title" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Organisateur</label>
                                <select class="form-select" id="ent-election-organizer">
                                    <option value="">Selectionner un organisateur</option>
                                </select>
                                <div class="form-text">Liste issue des utilisateurs actifs (roles admin/scrutateur).</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="ent-election-description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="ent-wizard-step d-none" data-step="2">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Type</label>
                                <select class="form-select" id="ent-election-type" required>
                                    <option value="SINGLE">SINGLE (1 choix)</option>
                                    <option value="MULTI">MULTI (N choix)</option>
                                    <option value="YESNO">YES/NO</option>
                                    <option value="RANKED">RANKED (classement)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Audience</label>
                                <select class="form-select" id="ent-election-audience" required>
                                    <option value="INTERNAL">INTERNAL (salariés)</option>
                                    <option value="HYBRID">HYBRID (interne + externe)</option>
                                    <option value="EXTERNAL">EXTERNAL (hors entreprise)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fuseau horaire</label>
                                <input class="form-control" id="ent-election-timezone" value="Europe/Paris" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Max choix (MULTI)</label>
                                <input type="number" class="form-control" id="ent-election-max-choices" min="1" placeholder="(vide = illimité)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Résultats côté votant</label>
                                <select class="form-select" id="ent-election-results-vis" required>
                                    <option value="AFTER_CLOSE">Après clôture uniquement</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Début</label>
                                <input type="datetime-local" class="form-control" id="ent-election-start" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fin</label>
                                <input type="datetime-local" class="form-control" id="ent-election-end" required>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="ent-election-anon">
                                    <label class="form-check-label" for="ent-election-anon">Vote anonyme</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="ent-election-mandatory">
                                    <label class="form-check-label" for="ent-election-mandatory">Vote obligatoire</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="ent-election-allow-change">
                                    <label class="form-check-label" for="ent-election-allow-change">Autoriser changement</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="ent-election-random-order">
                                    <label class="form-check-label" for="ent-election-random-order">Ordre aléatoire</label>
                                </div>
                            </div>
                            <div class="col-12" id="ent-election-candidates-wrap">
                                <label class="form-label">Candidats initiaux (utilisateurs existants)</label>
                                <select class="form-select" id="ent-election-candidate-users" multiple></select>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="ent-cands-select-all-internal">Selectionner internes</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="ent-cands-select-all-external">Selectionner externes</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="ent-cands-clear">Vider</button>
                                </div>
                                <div class="form-text">
                                    Selectionne les utilisateurs qui deviennent candidats a la creation.
                                    Pour des candidats externes, cree-les d'abord (Utilisateurs ou onglet Candidats), puis selectionne-les ici.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ent-wizard-step d-none" data-step="3">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Groupes éligibles</label>
                                <select class="form-select" id="ent-election-groups" multiple></select>
                                <div class="form-text">Si vide: tous les votants actifs de l’audience choisie.</div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    Astuce: tu peux figer l’éligibilité après publication via le module <strong>Émargement</strong> (snapshot).
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ent-wizard-step d-none" data-step="4">
                        <div class="alert alert-secondary">
                            <strong>Résumé</strong> — vérifie les paramètres avant d’enregistrer.
                        </div>
                        <div id="ent-election-summary" class="small"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-outline-secondary" type="button" id="ent-wiz-prev"><i class="bi bi-arrow-left me-1"></i>Précédent</button>
                    <button class="btn btn-outline-primary" type="button" id="ent-wiz-next">Suivant<i class="bi bi-arrow-right ms-1"></i></button>
                    <button class="btn btn-primary d-none" id="ent-wiz-submit"><i class="bi bi-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>






