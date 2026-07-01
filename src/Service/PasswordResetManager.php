<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordResetManager
{
    private const TOKEN_TTL = '+1 hour';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordResetTokenRepository $passwordResetTokens,
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function createToken(User $user): string
    {
        $now = new \DateTimeImmutable();
        $this->invalidateActiveTokensForUser($user, $now);

        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));

        $token = (new PasswordResetToken())
            ->setUser($user)
            ->setSelector($selector)
            ->setTokenHash($this->hashVerifier($verifier))
            ->setExpiresAt($now->modify(self::TOKEN_TTL));

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return sprintf('%s.%s', $selector, $verifier);
    }

    public function isTokenValid(string $tokenValue): bool
    {
        return $this->findValidToken($tokenValue) instanceof PasswordResetToken;
    }

    public function resetPassword(string $tokenValue, string $plainPassword): bool
    {
        $token = $this->findValidToken($tokenValue);

        if (!$token instanceof PasswordResetToken) {
            return false;
        }

        $user = $token->getUser();

        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $token->markUsed($now);
        $this->revokeUserSessions($user, $now);

        $this->entityManager->flush();

        return true;
    }

    private function findValidToken(string $tokenValue): ?PasswordResetToken
    {
        [$selector, $verifier] = $this->splitToken($tokenValue);

        if (null === $selector || null === $verifier) {
            return null;
        }

        $token = $this->passwordResetTokens->findUsableBySelector($selector, new \DateTimeImmutable());

        if (!$token instanceof PasswordResetToken || !hash_equals($token->getTokenHash(), $this->hashVerifier($verifier))) {
            return null;
        }

        return $token;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitToken(string $tokenValue): array
    {
        if (!str_contains($tokenValue, '.')) {
            return [null, null];
        }

        [$selector, $verifier] = explode('.', $tokenValue, 2);

        if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $verifier)) {
            return [null, null];
        }

        return [$selector, $verifier];
    }

    private function hashVerifier(string $verifier): string
    {
        return hash_hmac('sha256', $verifier, $this->secret);
    }

    private function invalidateActiveTokensForUser(User $user, \DateTimeImmutable $now): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(PasswordResetToken::class, 'token')
            ->set('token.usedAt', ':now')
            ->andWhere('token.user = :user')
            ->andWhere('token.usedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    private function revokeUserSessions(User $user, \DateTimeImmutable $now): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(UserSession::class, 'session')
            ->set('session.revokedAt', ':now')
            ->andWhere('session.user = :user')
            ->andWhere('session.revokedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
