<?php

namespace App\Controller\Front;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Mailer\SimpleMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
    ): RedirectResponse {
        $redirect = $this->safeRedirect($request->request->getString('redirect'));

        if (!$this->isCsrfTokenValid('newsletter_subscribe', $request->request->getString('_csrf_token'))) {
            $this->addFlash('newsletter_error', 'newsletter.flash.invalid');

            return $this->redirect($redirect);
        }

        $email = mb_strtolower(trim($request->request->getString('email')));
        $violations = $validator->validate($email, [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 180),
        ]);

        if (count($violations) > 0) {
            $this->addFlash('newsletter_error', 'newsletter.flash.invalid_email');

            return $this->redirect($redirect);
        }

        $subscriber = $subscribers->findOneByEmail($email);

        if ($subscriber instanceof NewsletterSubscriber && $subscriber->isActive()) {
            $this->addFlash('newsletter_success', 'newsletter.flash.already_subscribed');

            return $this->redirect($redirect);
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

        $this->addFlash('newsletter_success', 'newsletter.flash.subscribed');

        return $this->redirect($redirect);
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
