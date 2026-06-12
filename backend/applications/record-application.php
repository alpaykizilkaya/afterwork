<?php

declare(strict_types=1);

require_once __DIR__ . '/../analytics/request-context.php';
require_once __DIR__ . '/../notifications/notify.php';

/**
 * Record a job application AND everything that hangs off it, in one call.
 *
 * This is the single hook the seeker "Başvur" button should call:
 *   require_once __DIR__ . '/../../../backend/applications/record-application.php';
 *   record_application($pdo, $listingId, $seekerAccountId);
 *
 * It:
 *   1. captures the applicant's traffic source / device / coarse location
 *      (same context as a view, so Mercek can compare viewers vs applicants),
 *   2. inserts the application (idempotent — re-applying does not double count),
 *   3. notifies the employer's account so it lands in their topbar bell.
 *
 * Returns true if a NEW application was recorded, false if it already existed
 * or the write failed. Never throws — applying must not break on analytics.
 *
 * @param string|null $listingTitle Optional, only used to make the notification nicer.
 */
if (!function_exists('record_application')) {
    function record_application(PDO $pdo, int $listingId, int $seekerAccountId, ?string $listingTitle = null): bool
    {
        if ($listingId <= 0 || $seekerAccountId <= 0) {
            return false;
        }

        $ctx = aw_request_context();

        // Insert idempotently. UNIQUE(listing_id, seeker_account_id) means a repeat
        // application is a no-op (rowCount 0) and won't re-notify.
        $isNew = false;
        try {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO listing_applications
                    (listing_id, seeker_account_id, status, traffic_source, device_type, country_code, country, city, submitted_at)
                 VALUES (:l, :s, "submitted", :src, :dev, :cc, :co, :ci, NOW())'
            );
            $stmt->execute([
                'l'   => $listingId,
                's'   => $seekerAccountId,
                'src' => $ctx['traffic_source'],
                'dev' => $ctx['device_type'],
                'cc'  => $ctx['country_code'],
                'co'  => $ctx['country'],
                'ci'  => $ctx['city'],
            ]);
            $isNew = $stmt->rowCount() > 0;
        } catch (Throwable) {
            // Analytics columns not migrated yet — fall back to the bare insert so
            // applications still get recorded.
            try {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO listing_applications (listing_id, seeker_account_id, status, submitted_at)
                     VALUES (:l, :s, "submitted", NOW())'
                );
                $stmt->execute(['l' => $listingId, 's' => $seekerAccountId]);
                $isNew = $stmt->rowCount() > 0;
            } catch (Throwable) {
                return false;
            }
        }

        if (!$isNew) {
            return false;
        }

        // Notify the listing's employer account.
        try {
            $q = $pdo->prepare(
                'SELECT e.account_id, jl.title
                 FROM job_listings jl JOIN employers e ON e.id = jl.employer_id
                 WHERE jl.id = :l LIMIT 1'
            );
            $q->execute(['l' => $listingId]);
            if ($row = $q->fetch()) {
                $employerAccountId = (int) ($row['account_id'] ?? 0);
                $title = $listingTitle ?? (string) ($row['title'] ?? '');
                notify_account(
                    $pdo,
                    $employerAccountId,
                    'Yeni başvuru',
                    $title !== '' ? $title . ' ilanına yeni bir başvuru geldi.' : 'İlanlarından birine yeni bir başvuru geldi.',
                    '/basvuru.php?l=' . $listingId . '&s=' . $seekerAccountId
                );
            }
        } catch (Throwable) {
            // notification is best-effort
        }

        return true;
    }
}
