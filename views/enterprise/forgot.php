<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Mot de passe oublié';
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
<body class="auth-shell d-flex flex-column min-vh-100">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <main class="w-100 flex-grow-1 d-flex align-items-center" id="main" tabindex="-1">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5 col-xl-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h1 class="h5 mb-3 text-center">Mot de passe oublié</h1>

                            <?php if ($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
                            <?php if ($info): ?><div class="alert alert-info"><?=$info?></div><?php endif; ?>

                            <form method="post" autocomplete="off">
                                <?=csrf_field()?>
                                <div class="mb-3">
                                    <label class="form-label">Email ou username</label>
                                    <input class="form-control" name="login" required autofocus>
                                </div>
                                <button class="btn btn-primary w-100">Générer un lien</button>
                            </form>

                            <div class="text-center mt-3">
                                <a class="text-muted" href="<?=htmlspecialchars(app_url('/enterprise/login.php'))?>">Retour connexion</a>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3 small text-muted">En prod: envoyer via SMTP</div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>


