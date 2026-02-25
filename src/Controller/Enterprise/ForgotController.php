<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

final class ForgotController extends BasePortalController
{
    public function __invoke(): string
    {
        $pageTitle = 'Mot de passe oublié';
        $info = null;
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_post(false);
            $login = trim((string)($_POST['login'] ?? ''));
            if ($login === '') {
                $error = 'Renseigne un email ou username';
            } else {
                $user = user_load_by_login($this->pdo, $login);
                if (!$user || ($user['status'] ?? 'ACTIVE') !== 'ACTIVE') {
                    // Don't leak existence.
                    $info = 'Si ce compte existe, un lien de réinitialisation a été généré.';
                } else {
                    $raw = bin2hex(random_bytes(24));
                    $hash = hash('sha256', $raw);
                    $expires = (new \DateTimeImmutable('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');

                    $this->pdo->prepare('INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?,?,?)')
                        ->execute([(int)$user['id'], $hash, $expires]);

                    // Local “no budget” email simulation: show link directly.
                    $link = app_url('/enterprise/reset.php?token=' . urlencode($raw));
                    $info = 'Lien de réinitialisation (local) : ' . htmlspecialchars($link);
                }
            }
        }

        return $this->view->render('enterprise/forgot', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'info' => $info,
            'error' => $error,
        ]);
    }
}

