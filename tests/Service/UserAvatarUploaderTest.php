<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserAvatarUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserAvatarUploaderTest extends TestCase
{
    private Filesystem $filesystem;
    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sprintf('%s/ultrapop-avatar-test-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->filesystem, $this->directory)) {
            $this->filesystem->remove($this->directory);
        }
    }

    public function testUploadStoresAvatarAndRemovesPreviousFile(): void
    {
        $oldFilename = 'old-avatar.png';
        file_put_contents(sprintf('%s/%s', $this->directory, $oldFilename), 'old');

        $source = sprintf('%s/source.png', $this->directory);
        file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        $user = (new User())->setAvatarFilename($oldFilename);
        $uploader = new UserAvatarUploader($this->directory, $this->filesystem);

        $uploader->upload($user, new UploadedFile($source, 'avatar.png', 'image/png', null, true));

        self::assertNotNull($user->getAvatarFilename());
        self::assertStringEndsWith('.png', $user->getAvatarFilename());
        self::assertFileExists(sprintf('%s/%s', $this->directory, $user->getAvatarFilename()));
        self::assertFileDoesNotExist(sprintf('%s/%s', $this->directory, $oldFilename));
    }

    public function testUnsupportedAvatarMimeTypeIsRejected(): void
    {
        $source = sprintf('%s/source.txt', $this->directory);
        file_put_contents($source, 'not an image');

        $uploader = new UserAvatarUploader($this->directory, $this->filesystem);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('profile.avatar.flash.unsupported');

        $uploader->upload(new User(), new UploadedFile($source, 'avatar.txt', 'text/plain', null, true));
    }
}
