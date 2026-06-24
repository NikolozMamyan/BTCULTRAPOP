<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactMessage
{
    #[Assert\NotBlank(message: 'contact.validation.subject_required')]
    #[Assert\Length(max: 160, maxMessage: 'contact.validation.subject_too_long')]
    public string $subject = '';

    #[Assert\NotBlank(message: 'contact.validation.email_required')]
    #[Assert\Email(message: 'contact.validation.email_invalid')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'contact.validation.message_required')]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'contact.validation.message_too_short',
        maxMessage: 'contact.validation.message_too_long',
    )]
    public string $message = '';

    public ?string $website = null;
}
