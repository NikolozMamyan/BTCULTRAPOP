<?php

namespace App\Tests\Service;

use App\Service\WebComponentManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class WebComponentManagerTest extends TestCase
{
    public function testExistingBannersReceiveWebpVariantsAndTheirRealDimensions(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/ultrapop-seo-' . bin2hex(random_bytes(8));
        $uploads = $directory . '/public/uploads/web-components';
        $config = $directory . '/var/web_components/home.json';
        $filesystem->mkdir($uploads);

        try {
            $image = imagecreatetruecolor(1600, 600);
            imagejpeg($image, $uploads . '/banner.jpg', 95);
            imagedestroy($image);
            $filesystem->dumpFile($config, json_encode([
                'hero' => [['image' => 'uploads/web-components/banner.jpg', 'alt' => 'Test banner']],
            ], JSON_THROW_ON_ERROR));
            $manager = new WebComponentManager($config, $uploads, $filesystem, new AsciiSlugger('fr'));
            $manager->optimizeImages();

            $slide = $manager->home()['hero'][0];
            self::assertSame(1600, $slide['image_meta']['width']);
            self::assertSame(600, $slide['image_meta']['height']);
            self::assertSame([640, 1280], array_column($slide['image_meta']['variants'], 'width'));
            self::assertSame([640, 240], array_slice(getimagesize($uploads . '/banner.jpg.640.webp'), 0, 2));
            self::assertSame('image/webp', getimagesize($uploads . '/banner.jpg.1280.webp')['mime']);
            self::assertFileDoesNotExist($uploads . '/banner.jpg.1920.webp');
            self::assertFileExists($uploads . '/banner.jpg');
        } finally {
            $filesystem->remove($directory);
        }
    }
}
