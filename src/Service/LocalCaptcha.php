<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class LocalCaptcha
{
    private const SESSION_KEY = 'bot_captcha';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function create(SessionInterface $session): string
    {
        $answer = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $session->set(self::SESSION_KEY, [
            'answer' => hash_hmac('sha256', $answer, $this->secret),
            'expires' => time() + 300,
        ]);

        $image = imagecreatetruecolor(240, 90);
        $background = imagecolorallocate($image, 247, 249, 252);
        $noise = imagecolorallocate($image, 173, 187, 211);
        imagefill($image, 0, 0, $background);

        for ($index = 0; $index < 6; ++$index) {
            $glyph = imagecreatetruecolor(9, 15);
            $glyphBackground = imagecolorallocate($glyph, 247, 249, 252);
            imagefill($glyph, 0, 0, $glyphBackground);
            imagechar($glyph, 5, 0, 0, $answer[$index], imagecolorallocate($glyph, 32, 50, 99));
            $scaled = imagescale($glyph, 27, 45);
            $rotated = imagerotate($scaled, random_int(-12, 12), $glyphBackground);
            imagecopy($image, $rotated, 10 + $index * 37, random_int(15, 25), 0, 0, imagesx($rotated), imagesy($rotated));
            imagedestroy($glyph);
            imagedestroy($scaled);
            imagedestroy($rotated);
        }

        for ($index = 0; $index < 4; ++$index) {
            imageline($image, 0, random_int(5, 85), 240, random_int(5, 85), $noise);
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    public function verify(SessionInterface $session, string $answer): bool
    {
        $challenge = $session->remove(self::SESSION_KEY);
        $answer = trim($answer);

        return is_array($challenge)
            && ($challenge['expires'] ?? 0) > time()
            && preg_match('/^[0-9]{6}$/D', $answer)
            && is_string($challenge['answer'] ?? null)
            && hash_equals($challenge['answer'], hash_hmac('sha256', $answer, $this->secret));
    }
}
