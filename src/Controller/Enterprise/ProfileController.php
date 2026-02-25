<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

use PDOException;

final class ProfileController extends BasePortalController
{
    public function __invoke(): string
    {
        user_require_login(false);

        $pageTitle = 'Profil';
        $userId = user_current_id();
        if ($userId === null) {
            header('Location: ' . app_url('/enterprise/login.php'));
            exit;
        }

        $error = null;
        $info = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_post(false);
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $departement = trim((string)($_POST['departement'] ?? ($_POST['service'] ?? '')));
            $email = trim((string)($_POST['email'] ?? ''));
            $employeeId = trim((string)($_POST['employee_id'] ?? ''));

            if ($fullName === '') {
                $error = 'Nom requis';
            } else {
                try {
                    $stmt = $this->pdo->prepare('UPDATE users SET full_name=?, phone=?, service=?, email=?, employee_id=?, updated_at=NOW() WHERE id=?');
                    $stmt->execute([
                        $fullName,
                        $phone !== '' ? $phone : null,
                        $departement !== '' ? $departement : null,
                        $email !== '' ? mb_strtolower($email) : null,
                        $employeeId !== '' ? $employeeId : null,
                        $userId,
                    ]);
                    $info = 'Profil mis à jour';
                } catch (PDOException $e) {
                    if (($e->errorInfo[1] ?? null) === 1062) {
                        $error = 'Email ou matricule déjà utilisé';
                    } else {
                        $error = 'Erreur serveur';
                    }
                }
            }
        }

        $stmt = $this->pdo->prepare('SELECT username,email,full_name,phone,service AS departement,employee_id,status,must_reset_password FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $me = $stmt->fetch() ?: [];

        return $this->view->render('enterprise/profile', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'active' => 'profile',
            'error' => $error,
            'info' => $info,
            'me' => $me,
        ]);
    }
}
