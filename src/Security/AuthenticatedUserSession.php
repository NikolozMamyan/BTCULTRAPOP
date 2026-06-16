<?php

namespace App\Security;

use App\Entity\UserSession;

final readonly class AuthenticatedUserSession
{
    public function __construct(
        public UserSession $session,
        public string $token,
    ) {
    }
}
