<?php

namespace App\Controller\Front;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Mailer\SimpleMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class NewsletterController extends AbstractController
{
    #[Route('/newsletter/inscription', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterSubscriberRepository $subscribers,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        SimpleMailerService $mailer,
        LoggerInterface $logger,
        TranslatorInterface $translator,
    ): Response {
        $redirect = $this->safeRedirect($request->request->getString('redirect'));

        if (!$this->isCsrfTokenValid('newsletter_subscribe', $request->request->getString('_csrf_token'))) {
            return $this->subscriptionResponse(
                $request,
                $translator,
                $redirect,
                'newsletter.flash.invalid',
                false,
                Response::HTTP_FORBIDDEN,
            );
        }

        $email = mb_strtolower(trim($request->request->getString('email')));
        $violations = $validator->validate($email, [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 180),
        ]);

        if (count($violations) > 0) {
            return $this->subscriptionResponse(
                $request,
                $translator,
                $redirect,
                'newsletter.flash.invalid_email',
                false,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $subscriber = $subscribers->findOneByEmail($email);

        if ($subscriber instanceof NewsletterSubscriber && $subscriber->isActive()) {
            return $this->subscriptionResponse(
                $request,
                $translator,
                $redirect,
                'newsletter.flash.already_subscribed',
                true,
            );
        }

        $subscriber ??= (new NewsletterSubscriber())->setEmail($email);
        $subscriber
            ->setLocale($request->getLocale())
            ->setSource($request->request->getString('source'))
            ->subscribe();

        $entityManager->persist($subscriber);
        $entityManager->flush();

        try {
            $mailer->sendTemplateMessage(
                subject: 'Bienvenue dans la newsletter ULTRAPOP',
                htmlTemplate: 'emails/newsletter_welcome.html.twig',
                context: [
                    'subscriber' => $subscriber,
                ],
                textMessage: "Bienvenue dans la communauté ULTRAPOP !\n\nTu recevras désormais nos nouveautés, offres et sélections pop culture.",
                to: [$subscriber->getEmail()],
            );
        } catch (TransportExceptionInterface $exception) {
            $logger->error('Unable to send the newsletter welcome email.', [
                'subscriber_id' => $subscriber->getId(),
                'exception' => $exception,
            ]);
        }

        return $this->subscriptionResponse(
            $request,
            $translator,
            $redirect,
            'newsletter.flash.subscribed',
            true,
        );
    }

    private function subscriptionResponse(
        Request $request,
        TranslatorInterface $translator,
        string $redirect,
        string $message,
        bool $success,
        int $statusCode = Response::HTTP_OK,
    ): Response {
        if ($this->wantsJson($request)) {
            return new JsonResponse([
                'success' => $success,
                'message' => $translator->trans($message),
            ], $statusCode);
        }

        $this->addFlash($success ? 'newsletter_success' : 'newsletter_error', $message);

        return new RedirectResponse($redirect);
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    private function safeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);

        if ('' === $redirect || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return $this->generateUrl('app_front_home').'#newsletter';
        }

        return $redirect;
    }
}
