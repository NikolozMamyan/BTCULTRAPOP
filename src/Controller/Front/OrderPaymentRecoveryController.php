<?php

namespace App\Controller\Front;

use App\Entity\Order;
use App\Exception\StripeConfigurationException;
use App\Repository\OrderRepository;
use App\Service\OrderPaymentLinkSigner;
use App\Service\OrderPaymentRecoveryManager;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderPaymentRecoveryController extends AbstractController
{
    #[Route('/checkout/recovery/{id}', name: 'app_checkout_payment_recovery', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(
        Request $request,
        int $id,
        OrderRepository $orders,
        OrderPaymentLinkSigner $linkSigner,
        OrderPaymentRecoveryManager $recoveryManager,
    ): Response {
        if (!$linkSigner->isValid($request)) {
            return $this->errorResponse('checkout.recovery.invalid', Response::HTTP_GONE);
        }

        $order = $orders->find($id);

        if (!$order instanceof Order) {
            return $this->errorResponse('checkout.recovery.invalid', Response::HTTP_GONE);
        }

        try {
            return $this->redirect(
                $recoveryManager->resumePayment($order),
                Response::HTTP_SEE_OTHER,
            );
        } catch (StripeConfigurationException|ApiErrorException) {
            return $this->errorResponse('checkout.recovery.temporary', Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    private function errorResponse(string $message, int $status): Response
    {
        $response = $this->render('front/checkout/recovery_unavailable.html.twig', [
            'message' => $message,
        ]);
        $response->setStatusCode($status);

        return $response;
    }
}
