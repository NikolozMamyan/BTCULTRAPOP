<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\PopupEngagementTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PopupEngagementController extends AbstractController
{
    #[Route('/api/popup/engagement', name: 'app_api_popup_engagement', methods: ['POST'])]
    public function __invoke(
        Request $request,
        PopupEngagementTracker $engagementTracker,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid('popup_engagement', $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse([
                'message' => $translator->trans('auth.flash.invalid_csrf'),
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->getUser() instanceof User) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $event = is_string($payload['event'] ?? null) ? $payload['event'] : '';
        $version = is_string($payload['version'] ?? null) ? $payload['version'] : '';

        if (!in_array($event, [PopupEngagementTracker::EVENT_VIEWED, PopupEngagementTracker::EVENT_COPIED], true)
            || !preg_match('/^[a-f0-9]{40}$/', $version)
        ) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $engagementTracker->record($request, $event, $version);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
