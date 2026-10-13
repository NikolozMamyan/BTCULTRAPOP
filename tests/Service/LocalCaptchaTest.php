<?php

namespace App\Tests\Service;

use App\Service\LocalCaptcha;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class LocalCaptchaTest extends TestCase
{
    public function testItCreatesAPngAndStoresOnlyTheAnswerHash(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $captcha = new LocalCaptcha('test-secret');
        $image = $captcha->create($session);

        self::assertStringStartsWith('data:image/png;base64,', $image);
        $dimensions = getimagesizefromstring(base64_decode(substr($image, strlen('data:image/png;base64,')), true));
        self::assertSame(240, $dimensions[0]);
        self::assertSame(90, $dimensions[1]);
        self::assertSame('image/png', $dimensions['mime']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $session->get('bot_captcha')['answer']);
    }

    public function testACorrectAnswerCanOnlyBeUsedOnce(): void
    {
        $session = $this->sessionWithAnswer();
        $captcha = new LocalCaptcha('test-secret');

        self::assertTrue($captcha->verify($session, '012345'));
        self::assertFalse($captcha->verify($session, '012345'));
    }

    public function testAWrongAnswerInvalidatesTheChallenge(): void
    {
        $session = $this->sessionWithAnswer();
        $captcha = new LocalCaptcha('test-secret');

        self::assertFalse($captcha->verify($session, '999999'));
        self::assertFalse($captcha->verify($session, '012345'));
    }

    public function testAnExpiredChallengeIsRejected(): void
    {
        $session = $this->sessionWithAnswer(time() - 1);

        self::assertFalse((new LocalCaptcha('test-secret'))->verify($session, '012345'));
    }

    private function sessionWithAnswer(?int $expires = null): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('bot_captcha', [
            'answer' => hash_hmac('sha256', '012345', 'test-secret'),
            'expires' => $expires ?? time() + 300,
        ]);

        return $session;
    }
}
