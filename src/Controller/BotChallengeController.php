<?php

namespace App\Controller;

use App\Service\BotTrafficGuard;
use App\Service\LocalCaptcha;
use App\Service\StorefrontReturnUrlResolver;
use App\Service\VisitorEventRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BotChallengeController extends AbstractController
{
    #[Route('/verification', name: 'app_bot_challenge', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        BotTrafficGuard $guard,
        LocalCaptcha $captcha,
        StorefrontReturnUrlResolver $returnUrlResolver,
    ): Response {
        $returnTo = $returnUrlResolver->sanitize($request->isMethod('POST')
            ? $request->request->getString('return_to')
            : $request->query->getString('return_to'));
        $retryAfter = $guard->consumeChallengeAttempt($request);
        $error = null;

        if ($retryAfter > 0) {
            $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, 'rate_limited');
        }

        if (0 === $retryAfter && $request->isMethod('POST')) {
            $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, 'captcha_failed');
            if (!$this->isCsrfTokenValid('bot_challenge', $request->request->getString('_csrf_token'))) {
                $error = 'bot_protection.invalid_csrf';
            } elseif (!$captcha->verify($request->getSession(), $request->request->getString('answer'))) {
                $error = 'bot_protection.invalid_answer';
            } else {
                $request->attributes->set(VisitorEventRecorder::TYPE_ATTRIBUTE, 'captcha_passed');
                $response = $this->redirect($returnTo ?: $this->generateUrl('app_front_boutique'), Response::HTTP_SEE_OTHER);
                $response->headers->setCookie($guard->verificationCookie($request));
                $response->headers->set('Cache-Control', 'private, no-store');

                return $response;
            }
        }

        $status = $retryAfter > 0
            ? Response::HTTP_TOO_MANY_REQUESTS
            : (null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $this->render('security/bot_challenge.html.twig', [
            'image' => $retryAfter > 0 ? null : $captcha->create($request->getSession()),
            'return_to' => $returnTo,
            'error' => $error,
            'retry_after' => $retryAfter,
        ], new Response(status: $status));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        if ($retryAfter > 0) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }
}
