<?php

namespace App\Tests\Service;

use App\Model\ContactMessage;
use App\Service\ContactMessageSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ContactMessageSenderTest extends TestCase
{
    public function testItBuildsAReplyableCustomerServiceEmail(): void
    {
        $message = new ContactMessage();
        $message->subject = 'Suivi de commande';
        $message->email = 'client@example.com';
        $message->message = 'Bonjour, où se trouve ma commande ?';

        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sentEmail): void {
                $sentEmail = $email;
            });

        $twig = new Environment(new ArrayLoader([
            'emails/contact.txt.twig' => '{{ contact.subject }} / {{ contact.email }} / {{ contact.message }}',
            'emails/contact.html.twig' => '<p>{{ contact.message }}</p>',
        ]));

        (new ContactMessageSender(
            $mailer,
            $twig,
            'customer@ultrapop.com',
            'no-reply@ultrapop.com',
        ))->send($message);

        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('[ULTRAPOP] Suivi de commande', $sentEmail->getSubject());
        self::assertSame('customer@ultrapop.com', $sentEmail->getTo()[0]->getAddress());
        self::assertSame('client@example.com', $sentEmail->getReplyTo()[0]->getAddress());
        self::assertStringContainsString('où se trouve ma commande', (string) $sentEmail->getTextBody());
    }
}
