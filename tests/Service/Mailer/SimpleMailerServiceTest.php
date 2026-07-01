<?php

namespace App\Tests\Service\Mailer;

use App\Service\Mailer\SimpleMailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SimpleMailerServiceTest extends TestCase
{
    public function testItSendsATextMessageWithDefaultsAndHeaders(): void
    {
        $sentEmail = null;
        $service = $this->createService(
            $sentEmail,
            defaultRecipients: ['default@example.com'],
        );

        $service->sendTextMessage(
            subject: 'Sujet',
            message: 'Contenu texte',
            attachments: [[
                'content' => 'contenu du fichier',
                'name' => 'document.txt',
                'contentType' => 'text/plain',
            ]],
            cc: ['copie@example.com'],
            replyTo: ['reply@example.com'],
            bcc: ['cache@example.com'],
        );

        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('no-reply@ultrapop.com', $sentEmail->getFrom()[0]->getAddress());
        self::assertSame('default@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertSame('copie@example.com', $sentEmail->getCc()[0]->getAddress());
        self::assertSame('reply@example.com', $sentEmail->getReplyTo()[0]->getAddress());
        self::assertSame('cache@example.com', $sentEmail->getBcc()[0]->getAddress());
        self::assertSame('Sujet', $sentEmail->getSubject());
        self::assertSame('Contenu texte', $sentEmail->getTextBody());
        self::assertSame('document.txt', $sentEmail->getAttachments()[0]->getFilename());
    }

    public function testItRendersATemplateAndOverridesDefaultRecipients(): void
    {
        $sentEmail = null;
        $service = $this->createService(
            $sentEmail,
            templates: [
                'emails/example.html.twig' => '<h1>Bonjour {{ name }}</h1>',
            ],
            defaultRecipients: ['default@example.com'],
        );

        $service->sendTemplateMessage(
            subject: 'Bienvenue',
            htmlTemplate: 'emails/example.html.twig',
            context: ['name' => 'Nina'],
            textMessage: 'Bonjour Nina',
            to: ['nina@example.com'],
        );

        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('nina@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertSame('<h1>Bonjour Nina</h1>', $sentEmail->getHtmlBody());
        self::assertSame('Bonjour Nina', $sentEmail->getTextBody());
    }

    public function testItSendsARawHtmlMessage(): void
    {
        $sentEmail = null;
        $service = $this->createService($sentEmail);

        $service->sendHtmlMessage(
            subject: 'Campagne',
            htmlMessage: '<h1>Promo ULTRAPOP</h1>',
            textMessage: 'Promo ULTRAPOP',
            to: ['client@example.com'],
        );

        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('client@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertSame('Campagne', $sentEmail->getSubject());
        self::assertSame('<h1>Promo ULTRAPOP</h1>', $sentEmail->getHtmlBody());
        self::assertSame('Promo ULTRAPOP', $sentEmail->getTextBody());
    }

    public function testItAttachesAReadableFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ultrapop-mailer-');
        self::assertIsString($path);
        file_put_contents($path, 'facture');

        try {
            $sentEmail = null;
            $service = $this->createService(
                $sentEmail,
                defaultRecipients: ['default@example.com'],
            );

            $service->sendTextMessage(
                subject: 'Facture',
                message: 'Votre facture',
                attachments: [[
                    'path' => $path,
                    'name' => 'facture.txt',
                    'contentType' => 'text/plain',
                ]],
            );

            self::assertInstanceOf(Email::class, $sentEmail);
            self::assertSame('facture.txt', $sentEmail->getAttachments()[0]->getFilename());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testItRejectsAMessageWithoutRecipient(): void
    {
        $sentEmail = null;
        $service = $this->createService($sentEmail, shouldSend: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Aucun destinataire email');

        $service->sendTextMessage('Sujet', 'Message');
    }

    public function testItRejectsAnUnreadableAttachmentPath(): void
    {
        $sentEmail = null;
        $service = $this->createService(
            $sentEmail,
            defaultRecipients: ['default@example.com'],
            shouldSend: false,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('introuvable ou illisible');

        $service->sendTextMessage(
            subject: 'Sujet',
            message: 'Message',
            attachments: [[
                'path' => __DIR__.'/fichier-inexistant.pdf',
            ]],
        );
    }

    /**
     * @param array<string, string> $templates
     * @param list<string>          $defaultRecipients
     */
    private function createService(
        ?Email &$sentEmail,
        array $templates = [],
        array $defaultRecipients = [],
        bool $shouldSend = true,
    ): SimpleMailerService {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($shouldSend ? self::once() : self::never())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sentEmail): void {
                $sentEmail = $email;
            });

        return new SimpleMailerService(
            $mailer,
            new Environment(new ArrayLoader($templates)),
            'no-reply@ultrapop.com',
            $defaultRecipients,
        );
    }
}
