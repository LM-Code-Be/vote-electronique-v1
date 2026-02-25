<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

use Throwable;

final class PasswordController extends BasePortalController
{
    public function __invoke(): string
    {
        require_once APP_ROOT . '/app/audit.php';

        user_require_login(false);

        $pageTitle = 'Mot de passe';
        $userId = user_current_id();
        if ($userId === null) {
            header('Location: ' . app_url('/enterprise/login.php'));
            exit;
        }

        $error = null;
        $info = null;

        $stmt = $this->pdo->prepare('SELECT must_reset_password FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $mustReset = !empty($stmt->fetchColumn());

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_post(false);

            $current = (string)($_POST['current_password'] ?? '');
            $p1 = (string)($_POST['new_password'] ?? '');
            $p2 = (string)($_POST['new_password_confirm'] ?? '');

            if (strlen($p1) < 8) {
                $error = 'Mot de passe trop court (min 8)';
            } elseif ($p1 !== $p2) {
                $error = 'Confirmation différente';
            } else {
                $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
                $stmt->execute([$userId]);
                $hash = (string)($stmt->fetchColumn() ?: '');
                if ($hash === '' || !password_verify($current, $hash)) {
                    $error = 'Mot de passe actuel incorrect';
                } else {
                    $newHash = password_hash($p1, PASSWORD_DEFAULT);
                    if ($newHash === false) {
                        $error = 'Erreur serveur';
                    } else {
                        $this->pdo->beginTransaction();
                        try {
                            $this->pdo->prepare('UPDATE users SET password_hash=?, must_reset_password=0, updated_at=NOW() WHERE id=?')
                                ->execute([$newHash, $userId]);

                            // revoke other sessions (best-effort)
                            try {
                                $sid = session_id();
                                $this->pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL AND session_id<>?')
                                    ->execute([$userId, $sid]);
                            } catch (Throwable $e) {
                                // ignore
                            }

                            $this->pdo->commit();
                            audit_event($this->pdo, 'USER_CHANGE_PASSWORD', 'USER', $userId, []);
                            $info = 'Mot de passe modifié';
                            $mustReset = false;
                        } catch (Throwable $e) {
                            if ($this->pdo->inTransaction()) {
                                $this->pdo->rollBack();
                            }
                            $error = 'Erreur serveur';
                        }
                    }
                }
            }
        }

        return $this->view->render('enterprise/password', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'error' => $error,
            'info' => $info,
            'mustReset' => $mustReset,
        ]);
    }
}

