<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class UserAvatarUploader
{
    private const MAX_SIZE = 2_097_152;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        private string $avatarDirectory,
        private Filesystem $filesystem,
    ) {
    }

    public function upload(User $user, UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('profile.avatar.flash.invalid');
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('profile.avatar.flash.too_large');
        }

        $mimeType = $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if (null === $extension) {
            throw new \InvalidArgumentException('profile.avatar.flash.unsupported');
        }

        $this->filesystem->mkdir($this->avatarDirectory);

        $oldFilename = $user->getAvatarFilename();
        $filename = sprintf(
            'user-%s-%s.%s',
            $user->getId() ?? 'new',
            bin2hex(random_bytes(8)),
            $extension,
        );

        $file->move($this->avatarDirectory, $filename);
        $user->setAvatarFilename($filename);

        if (null !== $oldFilename && $oldFilename !== $filename) {
            $this->remove($oldFilename);
        }
    }

    private function remove(string $filename): void
    {
        $path = sprintf('%s/%s', rtrim($this->avatarDirectory, '/\\'), basename($filename));

        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}
