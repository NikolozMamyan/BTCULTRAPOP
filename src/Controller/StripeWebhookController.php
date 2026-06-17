<?php

namespace App\Controller;

use App\Exception\StripeConfigurationException;
use App\Service\StripeWebhookHandler;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request, StripeWebhookHandler $handler): JsonResponse
    {
        $signature = $request->headers->get('Stripe-Signature', '');

        try {
            $handler->handle($request->getContent(), $signature);
        } catch (SignatureVerificationException) {
            return $this->json(['error' => 'invalid_signature'], Response::HTTP_BAD_REQUEST);
        } catch (StripeConfigurationException) {
            return $this->json(['error' => 'webhook_not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(['received' => true]);
    }
}
