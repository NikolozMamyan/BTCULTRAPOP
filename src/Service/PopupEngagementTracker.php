<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

final readonly class PopupEngagementTracker
{
    public const EVENT_VIEWED = 'viewed';
    public const EVENT_COPIED = 'copied';

    public function __construct(
        private Connection $connection,
        private StorefrontPopupProvider $popupProvider,
    ) {
    }

    public function record(Request $request, string $event, string $version): bool
    {
        if (!in_array($event, [self::EVENT_VIEWED, self::EVENT_COPIED], true)) {
            return false;
        }

        $visitorToken = $request->cookies->getString(VisitorActivityTracker::COOKIE_NAME);

        if (!preg_match('/^[a-f0-9]{64}$/', $visitorToken)) {
            return false;
        }

        $popup = $this->popupProvider->popup();

        if (null === $popup || !hash_equals($popup['version'], $version)) {
            return false;
        }

        try {
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            return $this->connection->executeStatement(
                'UPDATE site_visitor SET
                    popup_promo_copied_at = CASE
                        WHEN :event = :copiedEvent THEN :now
                        WHEN popup_promo_version = :version THEN popup_promo_copied_at
                        ELSE NULL
                    END,
                    popup_promo_viewed_at = CASE
                        WHEN popup_promo_version = :version AND popup_promo_viewed_at IS NOT NULL THEN popup_promo_viewed_at
                        ELSE :now
                    END,
                    popup_promo_code = :code,
                    popup_promo_version = :version,
                    last_seen_at = :now,
                    hit_count = hit_count + 1,
                    human_score = GREATEST(human_score, 3),
                    suspected_bot = 0,
                    bot_reason = NULL
                WHERE visitor_token = :visitorToken AND visitor_type = :visitorType',
                [
                    'event' => $event,
                    'copiedEvent' => self::EVENT_COPIED,
                    'now' => $now,
                    'version' => $version,
                    'code' => $popup['code'],
                    'visitorToken' => $visitorToken,
                    'visitorType' => 'guest',
                ],
            ) > 0;
        } catch (\Throwable) {
            // Marketing tracking must never interfere with copying the code.
            return false;
        }
    }
}
