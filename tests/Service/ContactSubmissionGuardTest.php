<?php

namespace App\Tests\Service;

use App\Service\ContactSubmissionGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ContactSubmissionGuardTest extends TestCase
{
    public function testItRejectsAnImmediateSecondSubmission(): void
    {
        $guard = new ContactSubmissionGuard(new ArrayAdapter());

        self::assertTrue($guard->accept('127.0.0.1'));
        self::assertFalse($guard->accept('127.0.0.1'));
        self::assertTrue($guard->accept('127.0.0.2'));
    }
}
