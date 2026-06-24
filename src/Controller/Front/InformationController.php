<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\ContactType;
use App\Model\ContactMessage;
use App\Service\ContactSubmissionGuard;
use App\Service\Mailer\SimpleMailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class InformationController extends AbstractController
{
    #[Route('/livraison', name: 'app_front_delivery', methods: ['GET'])]
    public function delivery(): Response
    {
        return $this->render('front/information/delivery.html.twig');
    }

    #[Route('/retours-et-retractation', name: 'app_front_returns', methods: ['GET'])]
    public function returns(): Response
    {
        return $this->render('front/information/returns.html.twig');
    }

    #[Route('/conditions-generales-de-vente', name: 'app_front_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('front/information/terms.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_front_legal', methods: ['GET'])]
    public function legal(): Response
    {
        return $this->render('front/information/legal.html.twig');
    }

    #[Route('/politique-de-confidentialite', name: 'app_front_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('front/information/privacy.html.twig');
    }

    #[Route('/contact', name: 'app_front_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        SimpleMailerService $mailer,
        ContactSubmissionGuard $submissionGuard,
    ): Response {
        $message = new ContactMessage();
        $user = $this->getUser();

        if ($user instanceof User) {
            $message->email = $user->getEmail();
        }

        $form = $this->createForm(ContactType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('' !== trim((string) $message->website)) {
                return $this->redirectToRoute('app_front_contact');
            }

            if (!$submissionGuard->accept($request->getClientIp())) {
                $this->addFlash('error', 'contact.flash.too_many');

                return $this->redirectToRoute('app_front_contact');
            }

            try {
                $mailer->sendTemplateMessage(
                    subject: sprintf('[ULTRAPOP] %s', $message->subject),
                    htmlTemplate: 'emails/contact.html.twig',
                    context: [
                        'contact' => $message,
                    ],
                    textMessage: sprintf(
                        "Nouveau message depuis le formulaire ULTRAPOP\n\nObjet : %s\nEmail : %s\n\nMessage :\n%s",
                        $message->subject,
                        $message->email,
                        $message->message,
                    ),
                    replyTo: [$message->email],
                );
                $this->addFlash('success', 'contact.flash.sent');

                return $this->redirectToRoute('app_front_contact');
            } catch (TransportExceptionInterface) {
                $this->addFlash('error', 'contact.flash.error');
            }
        }

        return $this->render('front/information/contact.html.twig', [
            'contact_form' => $form,
        ]);
    }
}
