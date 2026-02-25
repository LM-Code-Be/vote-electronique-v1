<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

final class PreviewController extends BasePortalController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);

        $pageTitle = 'Apercu';
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

        $type = (string)($election['type'] ?? 'SINGLE');
        $candidates = in_array($type, ['SINGLE', 'MULTI', 'RANKED'], true)
            ? $this->elections()->candidates($this->pdo, $electionId, true)
            : [];
        if ($candidates && (($election['display_order_mode'] ?? 'MANUAL') === 'RANDOM')) {
            shuffle($candidates);
        }

        $open = $this->elections()->isOpen($election);

        return $this->view->render('enterprise/preview', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'active' => 'elections',
            'election' => $election,
            'open' => $open,
            'type' => $type,
            'candidates' => $candidates,
        ]);
    }
}
