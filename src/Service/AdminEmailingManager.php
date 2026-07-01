<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Mailer\SimpleMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class AdminEmailingManager
{
    public const AUDIENCE_ACTIVE_CUSTOMERS = 'active_customers';
    public const AUDIENCE_VERIFIED_CUSTOMERS = 'verified_customers';
    public const AUDIENCE_ALL_CUSTOMERS = 'all_customers';
    public const AUDIENCE_CUSTOM_SELECTION = 'custom_selection';

    /**
     * @var array<string, string>
     */
    private const AUDIENCES = [
        self::AUDIENCE_ACTIVE_CUSTOMERS => 'admin.emailing.audience.active_customers',
        self::AUDIENCE_VERIFIED_CUSTOMERS => 'admin.emailing.audience.verified_customers',
        self::AUDIENCE_ALL_CUSTOMERS => 'admin.emailing.audience.all_customers',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private SimpleMailerService $mailer,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function audiences(): array
    {
        return self::AUDIENCES;
    }

    /**
     * @return array<string, int>
     */
    public function audienceCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::AUDIENCES) as $audience) {
            $counts[$audience] = count($this->users->findForEmailingAudience($audience));
        }

        return $counts;
    }

    /**
     * @return list<array{id: int, name: string, email: string, active: bool, verified: bool}>
     */
    public function recipientChoices(): array
    {
        return array_map(
            static fn (User $user): array => [
                'id' => (int) $user->getId(),
                'name' => $user->getFullName() ?: $user->getEmail(),
                'email' => $user->getEmail(),
                'active' => $user->isActive(),
                'verified' => $user->isVerified(),
            ],
            $this->users->findForEmailingAudience(self::AUDIENCE_ALL_CUSTOMERS),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{template: EmailTemplate, recipient_count: int}
     *
     * @throws TransportExceptionInterface
     */
    public function createAndSend(User $admin, array $payload): array
    {
        $name = trim((string) ($payload['template_name'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $htmlContent = trim((string) ($payload['html_content'] ?? ''));
        $selectedUserIds = $this->normalizeIds($payload['selected_user_ids'] ?? []);
        $manualEmails = $this->normalizeEmails($payload['manual_emails'] ?? []);

        $this->validate($name, $subject, $htmlContent);

        $recipients = $this->recipientEmails($selectedUserIds, $manualEmails);

        if ([] === $recipients) {
            throw new \InvalidArgumentException('admin.emailing.flash.no_recipients');
        }

        $template = (new EmailTemplate())
            ->setName($name)
            ->setSubject($subject)
            ->setHtmlContent($htmlContent)
            ->setAudience(self::AUDIENCE_CUSTOM_SELECTION)
            ->setRecipientCount(count($recipients))
            ->setCreatedBy($admin);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        $textMessage = $this->htmlToText($htmlContent);

        foreach ($recipients as $recipient) {
            $this->mailer->sendHtmlMessage(
                subject: $subject,
                htmlMessage: $htmlContent,
                textMessage: $textMessage,
                to: [$recipient],
            );
        }

        $template->markSent();
        $this->entityManager->flush();

        return [
            'template' => $template,
            'recipient_count' => count($recipients),
        ];
    }

    /**
     * @param list<int>    $selectedUserIds
     * @param list<string> $manualEmails
     *
     * @return list<string>
     */
    private function recipientEmails(array $selectedUserIds, array $manualEmails): array
    {
        return array_values(array_unique(array_filter(
            [
                ...array_map(
                    static fn (User $user): string => $user->getEmail(),
                    $this->users->findForEmailingSelection($selectedUserIds),
                ),
                ...$manualEmails,
            ],
            static fn (string $email): bool => '' !== $email && false !== filter_var($email, FILTER_VALIDATE_EMAIL),
        )));
    }

    /**
     * @param mixed $value
     *
     * @return list<int>
     */
    private function normalizeIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $value),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private function normalizeEmails(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $emails = [];

        foreach ($value as $email) {
            $email = mb_strtolower(trim((string) $email));

            if ('' === $email) {
                continue;
            }

            if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('admin.emailing.flash.invalid_manual_email');
            }

            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    private function validate(string $name, string $subject, string $htmlContent): void
    {
        if ('' === $name || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('admin.emailing.flash.invalid_name');
        }

        if ('' === $subject || mb_strlen($subject) > 180) {
            throw new \InvalidArgumentException('admin.emailing.flash.invalid_subject');
        }

        if ('' === $htmlContent || mb_strlen($htmlContent) < 20) {
            throw new \InvalidArgumentException('admin.emailing.flash.invalid_html');
        }
    }

    private function htmlToText(string $htmlContent): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlContent) ?? $htmlContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
