<?php

namespace App\Service\Mailer;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class SimpleMailerService
{
    /**
     * @param list<string> $defaultRecipients
     */
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private string $defaultFrom,
        private array $defaultRecipients = [],
    ) {
    }

    /**
     * @param list<string> $to
     * @param list<array{path?: string, name?: string, contentType?: string, content?: string}> $attachments
     * @param list<string> $cc
     * @param list<string> $replyTo
     * @param list<string> $bcc
     *
     * @throws TransportExceptionInterface
     */
    public function sendTextMessage(
        string $subject,
        string $message,
        array $to = [],
        array $attachments = [],
        array $cc = [],
        array $replyTo = [],
        array $bcc = [],
    ): void {
        $email = (new Email())
            ->from($this->defaultFrom)
            ->subject($subject)
            ->text($message);

        $this->prepareEmail($email, $to, $attachments, $cc, $replyTo, $bcc);
        $this->mailer->send($email);
    }

    /**
     * @param list<string> $to
     * @param array<string, mixed> $context
     * @param list<array{path?: string, name?: string, contentType?: string, content?: string}> $attachments
     * @param list<string> $cc
     * @param list<string> $replyTo
     * @param list<string> $bcc
     *
     * @throws TransportExceptionInterface
     */
    public function sendTemplateMessage(
        string $subject,
        string $htmlTemplate,
        array $context = [],
        string $textMessage = '',
        array $to = [],
        array $attachments = [],
        array $cc = [],
        array $replyTo = [],
        array $bcc = [],
    ): void {
        $email = (new Email())
            ->from($this->defaultFrom)
            ->subject($subject)
            ->html($this->twig->render($htmlTemplate, $context));

        if ('' !== trim($textMessage)) {
            $email->text($textMessage);
        }

        $this->prepareEmail($email, $to, $attachments, $cc, $replyTo, $bcc);
        $this->mailer->send($email);
    }

    /**
     * @param list<string> $to
     * @param list<array{path?: string, name?: string, contentType?: string, content?: string}> $attachments
     * @param list<string> $cc
     * @param list<string> $replyTo
     * @param list<string> $bcc
     */
    private function prepareEmail(
        Email $email,
        array $to,
        array $attachments,
        array $cc,
        array $replyTo,
        array $bcc,
    ): void {
        $recipients = [] !== $to ? $to : $this->defaultRecipients;

        if ([] === $this->normalizeAddresses($recipients)) {
            throw new \InvalidArgumentException('Aucun destinataire email n’a été configuré.');
        }

        foreach ($this->normalizeAddresses($recipients) as $recipient) {
            $email->addTo($recipient);
        }

        foreach ($this->normalizeAddresses($cc) as $recipient) {
            $email->addCc($recipient);
        }

        foreach ($this->normalizeAddresses($replyTo) as $recipient) {
            $email->addReplyTo($recipient);
        }

        foreach ($this->normalizeAddresses($bcc) as $recipient) {
            $email->addBcc($recipient);
        }

        $this->addAttachments($email, $attachments);
    }

    /**
     * @param list<array{path?: string, name?: string, contentType?: string, content?: string}> $attachments
     */
    private function addAttachments(Email $email, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = trim((string) ($attachment['path'] ?? ''));

            if ('' !== $path) {
                if (!is_file($path) || !is_readable($path)) {
                    throw new \InvalidArgumentException(sprintf(
                        'La pièce jointe "%s" est introuvable ou illisible.',
                        $path,
                    ));
                }

                $email->attachFromPath(
                    $path,
                    $attachment['name'] ?? null,
                    $attachment['contentType'] ?? null,
                );

                continue;
            }

            if (array_key_exists('content', $attachment) && is_string($attachment['content'])) {
                $email->attach(
                    $attachment['content'],
                    $attachment['name'] ?? 'attachment',
                    $attachment['contentType'] ?? 'application/octet-stream',
                );
            }
        }
    }

    /**
     * @param list<string> $addresses
     *
     * @return list<string>
     */
    private function normalizeAddresses(array $addresses): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (string $address): string => trim($address),
                $addresses,
            ),
            static fn (string $address): bool => '' !== $address,
        )));
    }
}
