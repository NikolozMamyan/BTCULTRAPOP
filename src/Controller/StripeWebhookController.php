<?php

namespace App\Controller;

use App\Entity\Order;
use App\Enum\PaymentStatus;
use App\Exception\StripeConfigurationException;
use App\Service\Mailer\SimpleMailerService;
use App\Service\StripeWebhookHandler;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        StripeWebhookHandler $handler,
        SimpleMailerService $mailer,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $signature = $request->headers->get('Stripe-Signature', '');

        try {
            $order = $handler->handle($request->getContent(), $signature);

            if (
                $order instanceof Order
                && PaymentStatus::PAID === $order->getPaymentStatus()
                && null === $order->getConfirmationEmailSentAt()
                && null !== $order->getCustomerEmail()
            ) {
                $mailer->sendTemplateMessage(
                    subject: sprintf('Commande %s confirmée', $order->getOrderNumber()),
                    htmlTemplate: 'emails/order_confirmation.html.twig',
                    context: [
                        'order' => $order,
                    ],
                    textMessage: sprintf(
                        "Bonjour %s,\n\nLe paiement de ta commande %s a bien été validé.\nTotal : %.2f %s.\n\nNous préparons maintenant ton colis.",
                        $order->getCustomerName(),
                        $order->getOrderNumber(),
                        $order->getTotalTaxIncludedCents() / 100,
                        $order->getCurrency(),
                    ),
                    to: [$order->getCustomerEmail()],
                );
                $order->markConfirmationEmailSent();
                $entityManager->flush();
            }
        } catch (SignatureVerificationException) {
            return $this->json(['error' => 'invalid_signature'], Response::HTTP_BAD_REQUEST);
        } catch (StripeConfigurationException) {
            return $this->json(['error' => 'webhook_not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (TransportExceptionInterface) {
            return $this->json(['error' => 'confirmation_email_failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['received' => true]);
    }
}
