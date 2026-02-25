<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Profil';
/** @var string|null $active */
$active = isset($active) ? (string)$active : 'profile';
/** @var array $me */
$me = isset($me) && is_array($me) ? $me : [];
/** @var string|null $error */
$error = isset($error) && is_string($error) ? $error : null;
/** @var string|null $info */
$info = isset($info) && is_string($info) ? $info : null;
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
            <div class="d-flex justify-content-between align-items-center mb-3 page-header">
                <h1 class="h3 mb-0">Mon profil</h1>
                <span class="badge text-bg-<?=($me['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'success' : 'secondary'?>"><?=htmlspecialchars((string)($me['status'] ?? ''))?></span>
            </div>

            <?php include __DIR__ . '/partial/notifs.php'; ?>

            <?php if (!empty($me['must_reset_password'])): ?>
                <div class="alert alert-warning">
                    <strong>Action requise :</strong> tu dois changer ton mot de passe. <a href="<?=htmlspecialchars(app_url('/enterprise/password.php'))?>">Modifier maintenant</a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
            <?php if ($info): ?><div class="alert alert-success"><?=$info?></div><?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <?=csrf_field()?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input class="form-control" value="<?=htmlspecialchars((string)($me['username'] ?? ''))?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom complet</label>
                                <input class="form-control" name="full_name" value="<?=htmlspecialchars((string)($me['full_name'] ?? ''))?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input class="form-control" name="email" value="<?=htmlspecialchars((string)($me['email'] ?? ''))?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone</label>
                                <input class="form-control" name="phone" value="<?=htmlspecialchars((string)($me['phone'] ?? ''))?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Departement</label>
                                <input class="form-control" name="departement" value="<?=htmlspecialchars((string)($me['departement'] ?? $me['service'] ?? ''))?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Matricule / ID</label>
                                <input class="form-control" name="employee_id" value="<?=htmlspecialchars((string)($me['employee_id'] ?? ''))?>">
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h2 class="h5 mb-2">Mot de passe</h2>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">Change ton mot de passe (recommandé régulièrement).</div>
                        <a class="btn btn-outline-primary btn-sm" href="<?=htmlspecialchars(app_url('/enterprise/password.php'))?>"><i class="bi bi-shield-lock me-1"></i>Changer</a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1">Sessions</h2>
                        <div class="text-muted small">Déconnexion globale (toutes les sessions actives).</div>
                    </div>
                    <a class="btn btn-outline-danger" href="<?=htmlspecialchars(app_url('/enterprise/logout_all.php?csrf=' . urlencode(csrf_token())))?>" onclick="return confirm('Déconnecter toutes tes sessions ?');">
                        <i class="bi bi-box-arrow-right me-1"></i>Déconnexion globale
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>



