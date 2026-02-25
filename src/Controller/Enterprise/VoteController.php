<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

use Throwable;

final class VoteController extends BasePortalController
{
    public function __invoke(): string
    {
        user_require_login(false);

        $pageTitle = 'Voter';
        $userId = $this->auth()->currentId();
        if ($userId === null) {
            header('Location: ' . app_url('/enterprise/login.php'));
            exit;
        }

        $electionId = (int)($_GET['id'] ?? 0);
        if ($electionId <= 0) {
            http_response_code(404);
            return 'Scrutin introuvable';
        }

        $election = $this->elections()->get($this->pdo, $electionId);
        if (!$election) {
            http_response_code(404);
            return 'Scrutin introuvable';
        }

        $error = null;
        $success = null;
        $receipt = null;

        $open = $this->elections()->isOpen($election);
        $eligible = $this->elections()->isUserEligible($this->pdo, $electionId, $userId);
        $voted = $this->elections()->userHasParticipated($this->pdo, $electionId, $userId);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_post(false);
            if (!$open) {
                $error = 'Vote ferme';
            } elseif (!$eligible) {
                $error = 'Non eligible';
            } else {
                $payload = [
                    'choice' => $_POST['choice'] ?? null,
                    'choices' => $_POST['choices'] ?? null,
                    'yesno' => $_POST['yesno'] ?? null,
                    'ranking' => $_POST['ranking'] ?? null,
                ];
                try {
                    $res = $this->elections()->castBallot($this->pdo, $election, $userId, $payload);
                    $success = 'Vote enregistre';
                    $receipt = $res['receipt'] ?? null;
                    $voted = true;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $type = (string)($election['type'] ?? 'SINGLE');
        $candidates = in_array($type, ['SINGLE', 'MULTI', 'RANKED'], true)
            ? $this->elections()->candidates($this->pdo, $electionId, true)
            : [];
        if ($candidates && (($election['display_order_mode'] ?? 'MANUAL') === 'RANDOM')) {
            shuffle($candidates);
        }

        return $this->view->render('enterprise/vote', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'active' => 'elections',
            'election' => $election,
            'open' => $open,
            'eligible' => $eligible,
            'voted' => $voted,
            'error' => $error,
            'success' => $success,
            'receipt' => $receipt,
            'type' => $type,
            'candidates' => $candidates,
        ]);
    }
}
