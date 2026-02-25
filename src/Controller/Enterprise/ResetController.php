<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

use Throwable;

final class ResetController extends BasePortalController
{
    public function __invoke(): string
    {
        $pageTitle = 'Réinitialiser';
        $token = (string)($_GET['token'] ?? '');
        $error = null;
        $info = null;

        if ($token === '') {
            http_response_code(400);
            return 'Token manquant';
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_post(false);
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password_confirm'] ?? '');
            if ($p1 === '' || strlen($p1) < 8) {
                $error = 'Mot de passe trop court (min 8)';
            } elseif ($p1 !== $p2) {
                $error = 'Confirmation différente';
            } else {
                $hashToken = hash('sha256', $token);
                $stmt = $this->pdo->prepare("
                    SELECT pr.id, pr.user_id
                    FROM password_resets pr
                    WHERE pr.token_hash=?
                      AND pr.used_at IS NULL
                      AND pr.expires_at > NOW()
                    ORDER BY pr.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$hashToken]);
                $row = $stmt->fetch();
                if (!$row) {
                    $error = 'Token invalide ou expiré';
                } else {
                    $newHash = password_hash($p1, PASSWORD_DEFAULT);
                    if ($newHash === false) {
                        $error = 'Erreur de hash';
                    } else {
                        $this->pdo->beginTransaction();
                        $uid = (int)$row['user_id'];
                        $this->pdo->prepare('UPDATE users SET password_hash=?, must_reset_password=0, updated_at=NOW() WHERE id=?')
                            ->execute([$newHash, $uid]);
                        $this->pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
                        try {
                            $this->pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$uid]);
                        } catch (Throwable $e) {
                            // ignore
                        }
                        $this->pdo->commit();
                        $info = 'Mot de passe modifié. Tu peux te connecter.';
                    }
                }
            }
        }

        return $this->view->render('enterprise/reset', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'token' => $token,
            'error' => $error,
            'info' => $info,
        ]);
    }
}

