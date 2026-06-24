<?php

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscriber')]
#[ORM\UniqueConstraint(name: 'UNIQ_NEWSLETTER_EMAIL', columns: ['email'])]
#[UniqueEntity(fields: ['email'])]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Assert\Choice(choices: ['fr', 'en'])]
    private string $locale = 'fr';

    #[ORM\Column(length: 20, options: ['default' => 'footer'])]
    #[Assert\Choice(choices: ['home', 'footer'])]
    private string $source = 'footer';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $subscribedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __construct()
    {
        $this->subscribedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = in_array($source, ['home', 'footer'], true) ? $source : 'footer';

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function subscribe(?\DateTimeImmutable $subscribedAt = null): self
    {
        $this->active = true;
        $this->subscribedAt = $subscribedAt ?? new \DateTimeImmutable();
        $this->unsubscribedAt = null;

        return $this;
    }

    public function unsubscribe(?\DateTimeImmutable $unsubscribedAt = null): self
    {
        $this->active = false;
        $this->unsubscribedAt = $unsubscribedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function getSubscribedAt(): \DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }
}
