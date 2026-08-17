<?php

namespace App\EventSubscriber;

use App\Entity\StoreSettings;
use App\Entity\User;
use App\Repository\StoreSettingsRepository;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class StoreMaintenanceSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_ROUTES = [
        'app_auth_forgot_password',
        'app_auth_forgot_password_submit',
        'app_auth_login',
        'app_auth_logout',
        'app_auth_reset_password',
        'app_auth_reset_password_submit',
        'app_seo_robots',
        'app_stripe_webhook',
    ];

    public function __construct(
        private StoreSettingsRepository $settings,
        private TokenStorageInterface $tokenStorage,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Routing runs at priority 32 and authentication at priority 8.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if ('' === $route || str_starts_with($route, '_') || str_starts_with($route, 'app_admin_')) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if ('app_front_profil' === $route && !$user instanceof User) {
            return;
        }

        if (in_array($route, self::PUBLIC_ROUTES, true)) {
            return;
        }

        try {
            $settings = $this->settings->findCurrent();
        } catch (DbalException) {
            // Deploying the code before its migration must not accidentally close the shop.
            return;
        }

        if (!$settings instanceof StoreSettings || !$settings->isMaintenanceEnabled()) {
            return;
        }

        $response = str_starts_with($route, 'app_api_')
            ? $this->apiResponse($settings)
            : $this->htmlResponse($settings);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $event->setResponse($response);
    }

    private function htmlResponse(StoreSettings $settings): Response
    {
        return new Response(
            $this->twig->render('maintenance/index.html.twig', [
                'settings' => $settings,
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function apiResponse(StoreSettings $settings): JsonResponse
    {
        return new JsonResponse([
            'error' => 'storefront_maintenance',
            'message' => $this->translator->trans('maintenance.api_message'),
            'starts_at' => $settings->getMaintenanceStartsAt()?->format(\DateTimeInterface::ATOM),
            'ends_at' => $settings->getMaintenanceEndsAt()?->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
