<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Connexion';
/** @var string|null $error */
$error = isset($error) && is_string($error) ? $error : null;
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
                            <div class="auth-brand text-center">
                                <a href="https://lm-code.be" target="_blank" rel="noopener noreferrer">
                                    <img class="lm-brand-rect" src="<?=htmlspecialchars(app_url('/assets/brand/lm-code/logo-rect.png'))?>" alt="LM-Code">
                                </a>
                            </div>
                            <h1 class="h4 mb-1 text-center">Vote Enterprise</h1>
                            <p class="text-muted text-center mb-3">Connexion</p>

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?=$error?></div>
                            <?php endif; ?>

                            <form method="post" autocomplete="off">
                                <?=csrf_field()?>
                                <div class="mb-3">
                                    <label class="form-label">Email ou username</label>
                                    <input class="form-control" name="login" required autofocus>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mot de passe</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button class="btn btn-primary w-100">Se connecter</button>
                            </form>

                            <div class="d-flex justify-content-between mt-3">
                                <a class="text-muted" href="<?=htmlspecialchars(app_url('/enterprise/forgot.php'))?>">Mot de passe oublié</a>
                                <a class="text-muted" href="https://lm-code.be" target="_blank" rel="noopener noreferrer">LM-Code</a>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3 small text-muted">Entreprise • Single-tenant</div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/partial/footer.php'; ?>
    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>


