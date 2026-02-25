<?php
declare(strict_types=1);

namespace Vote\Domain\Election;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class ElectionRules
{
    public function isOpen(array $election): bool
    {
        if (($election['status'] ?? '') !== 'PUBLISHED') {
            return false;
        }

        try {
            $tz = new DateTimeZone((string)($election['timezone'] ?? 'UTC'));
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }

        $now = new DateTimeImmutable('now', $tz);
        $start = new DateTimeImmutable((string)$election['start_at'], $tz);
        $end = new DateTimeImmutable((string)$election['end_at'], $tz);

        return $now >= $start && $now <= $end;
    }

    public function phase(array $election): string
    {
        $status = (string)($election['status'] ?? 'DRAFT');
        if (in_array($status, ['CLOSED', 'ARCHIVED'], true)) {
            return 'past';
        }

        try {
            $tz = new DateTimeZone((string)($election['timezone'] ?? 'UTC'));
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }

        $now = new DateTimeImmutable('now', $tz);
        $start = new DateTimeImmutable((string)$election['start_at'], $tz);
        $end = new DateTimeImmutable((string)$election['end_at'], $tz);

        if ($now < $start) {
            return 'upcoming';
        }
        if ($now > $end) {
            return 'past';
        }

        return 'ongoing';
    }
}
