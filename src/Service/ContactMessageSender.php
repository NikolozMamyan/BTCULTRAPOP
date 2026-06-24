<?php

namespace App\Service;

use App\Model\ContactMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class ContactMessageSender
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        #[Autowire('%env(CONTACT_RECIPIENT)%')]
        private string $recipient,
        #[Autowire('%env(CONTACT_FROM)%')]
        private string $from,
    ) {
    }

    public function send(ContactMessage $message): void
    {
        $email = (new Email())
            ->from(new Address($this->from, 'ULTRAPOP'))
            ->to($this->recipient)
            ->replyTo($message->email)
            ->subject(sprintf('[ULTRAPOP] %s', $message->subject))
            ->text($this->twig->render('emails/contact.txt.twig', [
                'contact' => $message,
            ]))
            ->html($this->twig->render('emails/contact.html.twig', [
                'contact' => $message,
            ]));

        $this->mailer->send($email);
    }
}
