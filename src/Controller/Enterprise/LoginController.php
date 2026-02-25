<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

use Throwable;

final class LoginController extends BasePortalController
{
    public function __invoke(): string
    {
        require_once APP_ROOT . '/app/logger.php';

        $pageTitle = 'Connexion';
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_post(false);
            $login = trim((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            if (!user_login_allowed($this->pdo, $login, is_string($ip) ? $ip : null)) {
                $error = 'Trop de tentatives. Réessaie plus tard.';
            } else {
                $user = user_load_by_login($this->pdo, $login);
                $ok = false;
                if ($user && ($user['status'] ?? 'ACTIVE') === 'ACTIVE') {
                    $ok = password_verify($password, (string)$user['password_hash']);
                }

                try {
                    $this->pdo->prepare('INSERT INTO login_attempts(username_or_email,ip,success) VALUES(?,?,?)')
                        ->execute([mb_strtolower($login), $ip, $ok ? 1 : 0]);
                } catch (Throwable $e) {
                    // ignore
                }

                if ($ok && $user) {
                    $roles = user_load_roles($this->pdo, (int)$user['id']);
                    $allowedPortalRoles = ['VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'];
                    // Better UX than a raw 403 right after login.
                    if (!array_intersect($allowedPortalRoles, $roles)) {
                        $error = 'Compte sans rôle d’accès. Contacte un administrateur.';
                        return $this->view->render('enterprise/login', $this->portalContext() + [
                            'pageTitle' => $pageTitle,
                            'error' => $error,
                        ]);
                    }
                    user_login($this->pdo, $user, $roles);
                    log_action($this->pdo, 'login_success', (string)($user['email'] ?? null), $login);

                    if (!empty($user['must_reset_password'])) {
                        header('Location: ' . app_url('/enterprise/password.php'));
                        exit;
                    }

                    if (in_array('ADMIN', $roles, true) || in_array('SUPERADMIN', $roles, true) || in_array('SCRUTATEUR', $roles, true)) {
                        header('Location: ' . app_url('/enterprise/admin/dashboard.php'));
                        exit;
                    }
                    header('Location: ' . app_url('/enterprise/elections.php'));
                    exit;
                }

                $error = 'Identifiants incorrects';
            }
        }

        return $this->view->render('enterprise/login', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'error' => $error,
        ]);
    }
}
